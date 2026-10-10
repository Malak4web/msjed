<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Custodian;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\LedgerEntry;
use App\Models\RecurringExpensePeriod;
use App\Models\User;
use App\Notifications\ExpenseRequestNotification;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * دورة حياة طلب الصرف:
 * بانتظار الموافقة → معتمد (المبلغ محجوز) → تم التنفيذ (الخصم الفعلي من الرصيد)
 *                  ↘ مرفوض   ↘ مُرجَع للتعديل → بانتظار الموافقة
 * معتمد → مُلغى
 */
class ExpenseRequestService
{
    public function __construct(
        private LedgerService $ledger,
        private SequenceService $sequences,
        private PeriodLockService $periods,
        private AttachmentService $attachments,
        private SettingsService $settings,
    ) {}

    /**
     * @param  UploadedFile[]  $files
     */
    public function create(array $data, User $user, array $files = [], ?RecurringExpensePeriod $period = null): ExpenseRequest
    {
        $request = DB::transaction(function () use ($data, $user, $files, $period) {
            $custodian = $this->ledger->lockCustodians([$data['custodian_id']])->first();
            $this->assertCustodianActive($custodian);
            $category = ExpenseCategory::findOrFail($data['expense_category_id']);
            $amount = Money::cents($data['amount']);

            $this->ledger->assertAvailable($custodian, $amount);

            $request = ExpenseRequest::create([
                'number' => $this->sequences->next('EX'),
                'custodian_id' => $custodian->id,
                'expense_category_id' => $category->id,
                'type' => $category->type,
                'amount' => Money::decimal($amount),
                'description' => $data['description'],
                'status' => ExpenseRequest::PENDING,
                'requested_by' => $user->id,
                'recurring_expense_period_id' => $period?->id,
            ]);

            $this->event($request, 'created', $user);

            foreach ($files as $file) {
                $this->attachments->store($request, $file, 'quote', $user->id);
            }

            if ($reason = $this->autoApprovalReason($request, $user, $period)) {
                $request->forceFill([
                    'status' => ExpenseRequest::APPROVED,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'auto_approved' => true,
                    'auto_approval_reason' => $reason,
                ])->save();
                $this->event($request, 'auto_approved', $user, $reason);
            }

            if ($period) {
                $period->update(['expense_request_id' => $request->id]);
            }

            return $request;
        });

        if ($request->status === ExpenseRequest::PENDING) {
            Notification::send($this->approversFor($request), new ExpenseRequestNotification($request, 'submitted'));
        }

        return $request;
    }

    /** تعديل طلب مُرجَع (أو لسه مستني) — بيرجع للإمام تاني */
    public function update(ExpenseRequest $request, array $data, User $user, array $files = []): ExpenseRequest
    {
        $wasReturned = false;

        $request = DB::transaction(function () use ($request, $data, $user, $files, &$wasReturned) {
            $request = $this->lockRequest($request, [ExpenseRequest::PENDING, ExpenseRequest::RETURNED]);
            $wasReturned = $request->status === ExpenseRequest::RETURNED;

            $custodianId = $data['custodian_id'] ?? $request->custodian_id;
            $custodian = $this->ledger->lockCustodians([$custodianId])->first();
            $this->assertCustodianActive($custodian);
            $category = ExpenseCategory::findOrFail($data['expense_category_id'] ?? $request->expense_category_id);
            $amount = Money::cents($data['amount'] ?? $request->amount);

            $this->ledger->assertAvailable($custodian, $amount);

            $request->fill([
                'custodian_id' => $custodian->id,
                'expense_category_id' => $category->id,
                'type' => $category->type,
                'amount' => Money::decimal($amount),
                'description' => $data['description'] ?? $request->description,
                'status' => ExpenseRequest::PENDING,
            ])->save();

            foreach ($files as $file) {
                $this->attachments->store($request, $file, 'quote', $user->id);
            }

            if ($wasReturned) {
                $this->event($request, 'resubmitted', $user);
            }

            return $request;
        });

        if ($wasReturned) {
            Notification::send($this->approversFor($request), new ExpenseRequestNotification($request, 'submitted'));
        }

        return $request;
    }

    public function approve(ExpenseRequest $request, User $user, ?string $note = null): ExpenseRequest
    {
        $this->assertCanDecide($request, $user);

        $request = DB::transaction(function () use ($request, $user, $note) {
            $request = $this->lockRequest($request, [ExpenseRequest::PENDING]);
            $custodian = $this->ledger->lockCustodians([$request->custodian_id])->first();
            $this->ledger->assertAvailable($custodian, Money::cents($request->amount));

            $request->forceFill([
                'status' => ExpenseRequest::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ])->save();
            $this->event($request, 'approved', $user, $note);

            return $request;
        });

        $this->notifyRequester($request, 'approved');

        return $request;
    }

    public function reject(ExpenseRequest $request, User $user, string $reason): ExpenseRequest
    {
        $this->assertCanDecide($request, $user);

        $request = DB::transaction(function () use ($request, $user, $reason) {
            $request = $this->lockRequest($request, [ExpenseRequest::PENDING]);
            $request->forceFill(['status' => ExpenseRequest::REJECTED, 'rejection_reason' => $reason])->save();
            $this->event($request, 'rejected', $user, $reason);

            return $request;
        });

        $this->notifyRequester($request, 'rejected');

        return $request;
    }

    public function returnForEdit(ExpenseRequest $request, User $user, string $reason): ExpenseRequest
    {
        $this->assertCanDecide($request, $user);

        $request = DB::transaction(function () use ($request, $user, $reason) {
            $request = $this->lockRequest($request, [ExpenseRequest::PENDING]);
            $request->forceFill(['status' => ExpenseRequest::RETURNED, 'return_reason' => $reason])->save();
            $this->event($request, 'returned', $user, $reason);

            return $request;
        });

        $this->notifyRequester($request, 'returned');

        return $request;
    }

    /**
     * التنفيذ: هنا بس بيتخصم المبلغ من الرصيد.
     * المبلغ الفعلي ممكن يقل عن المعتمد، لكن مينفعش يزيد (محتاج طلب جديد).
     */
    public function execute(ExpenseRequest $request, User $user, array $data, ?UploadedFile $invoice = null): ExpenseRequest
    {
        $date = $data['executed_date'] ?? now()->toDateString();
        $this->periods->assertOpen($date);

        if (! $invoice && $this->settings->get('require_invoice_on_execute')) {
            throw new BusinessRuleException('لازم ترفع صورة الفاتورة عشان تأكد التنفيذ.');
        }

        return DB::transaction(function () use ($request, $user, $data, $invoice, $date) {
            $request = $this->lockRequest($request, [ExpenseRequest::APPROVED]);
            $custodian = $this->ledger->lockCustodians([$request->custodian_id])->first();

            $approved = Money::cents($request->amount);
            $actual = Money::cents($data['actual_amount'] ?? $request->amount);

            if ($actual <= 0) {
                throw new BusinessRuleException('المبلغ الفعلي لازم يكون أكبر من صفر.');
            }
            if ($actual > $approved) {
                throw new BusinessRuleException(sprintf(
                    'المبلغ الفعلي (%s) أكبر من المعتمد (%s). اعمل طلب صرف جديد بالفرق عشان الإمام يوافق عليه.',
                    Money::format(Money::decimal($actual)),
                    Money::format($request->amount)
                ));
            }

            $this->ledger->assertAvailable($custodian, $actual, $request->id);

            $request->forceFill([
                'status' => ExpenseRequest::EXECUTED,
                'actual_amount' => Money::decimal($actual),
                'executed_at' => now(),
                'executed_date' => $date,
                'executed_by' => $user->id,
            ])->save();

            $request->loadMissing('category');
            $this->ledger->post(
                $custodian,
                LedgerEntry::EXPENSE,
                -$actual,
                $date,
                "مصروف: {$request->category->name} — {$request->description}",
                $request,
                $user->id,
            );

            if ($invoice) {
                $this->attachments->store($request, $invoice, 'invoice', $user->id);
            }

            if ($request->recurring_expense_period_id) {
                RecurringExpensePeriod::whereKey($request->recurring_expense_period_id)
                    ->update(['status' => RecurringExpensePeriod::PAID]);
            }

            $this->event($request, 'executed', $user, null, [
                'actual_amount' => $request->actual_amount,
                'approved_amount' => $request->amount,
            ]);

            return $request;
        });
    }

    public function cancel(ExpenseRequest $request, User $user, string $reason): ExpenseRequest
    {
        return DB::transaction(function () use ($request, $user, $reason) {
            $request = $this->lockRequest($request, [ExpenseRequest::PENDING, ExpenseRequest::RETURNED, ExpenseRequest::APPROVED]);
            $request->forceFill(['status' => ExpenseRequest::CANCELLED, 'cancel_reason' => $reason])->save();
            $this->releaseRecurringPeriod($request);
            $this->event($request, 'cancelled', $user, $reason);

            return $request;
        });
    }

    /** إلغاء مصروف اتنفذ بقيد عكسي — الفلوس بترجع لعهدة الشخص */
    public function reverse(ExpenseRequest $request, User $user, string $reason): ExpenseRequest
    {
        $today = now()->toDateString();
        $this->periods->assertOpen($today);

        return DB::transaction(function () use ($request, $user, $reason, $today) {
            $request = $this->lockRequest($request, [ExpenseRequest::EXECUTED]);

            if ($request->isReversed()) {
                throw new BusinessRuleException('المصروف ده اتلغى بقيد عكسي قبل كده.');
            }

            $custodian = $this->ledger->lockCustodians([$request->custodian_id])->first();
            $this->ledger->post(
                $custodian,
                LedgerEntry::REVERSAL,
                Money::cents($request->actual_amount),
                $today,
                "قيد عكسي لمصروف {$request->number} — {$reason}",
                $request,
                $user->id,
            );

            $request->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $user->id,
                'reversal_reason' => $reason,
            ])->save();
            $this->releaseRecurringPeriod($request);
            $this->event($request, 'reversed', $user, $reason);

            return $request;
        });
    }

    /** مين له يوافق على الطلب ده؟ الإمام بس — إلا طلب الإمام نفسه لو الإعداد "يحتاج موافقة المحاسب" */
    public function canDecide(ExpenseRequest $request, User $user): bool
    {
        if ($request->status !== ExpenseRequest::PENDING || $request->requested_by === $user->id) {
            return false;
        }

        if ($this->isImamSelfRequest($request)) {
            return $user->hasRole(User::ROLE_ACCOUNTANT);
        }

        return $user->isImam();
    }

    /** @return Collection<int, User> */
    public function approversFor(ExpenseRequest $request): Collection
    {
        $role = $this->isImamSelfRequest($request) ? User::ROLE_ACCOUNTANT : User::ROLE_IMAM;

        return User::role($role)->where('is_active', true)->where('id', '!=', $request->requested_by)->get();
    }

    private function isImamSelfRequest(ExpenseRequest $request): bool
    {
        $requester = $request->relationLoaded('requester') ? $request->requester : User::find($request->requested_by);

        return (bool) $requester?->isImam();
    }

    private function autoApprovalReason(ExpenseRequest $request, User $user, ?RecurringExpensePeriod $period): ?string
    {
        if ($period && $period->recurringExpense?->pre_approved) {
            return 'بند ثابت معتمد مسبقًا';
        }

        if ($user->isImam() && $this->settings->get('imam_self_request_mode') === 'auto') {
            return 'طلب الإمام نفسه — موافقة تلقائية';
        }

        return null;
    }

    private function assertCanDecide(ExpenseRequest $request, User $user): void
    {
        if ($request->status !== ExpenseRequest::PENDING) {
            throw new BusinessRuleException('الطلب ده مش مستني موافقة دلوقتي.');
        }

        if (! $this->canDecide($request, $user)) {
            throw new BusinessRuleException('الموافقة أو الرفض من صلاحية إمام المسجد بس.', 403);
        }
    }

    private function assertCustodianActive(?Custodian $custodian): void
    {
        if (! $custodian || ! $custodian->is_active) {
            throw new BusinessRuleException('الشخص ده مش نشط.');
        }
    }

    private function lockRequest(ExpenseRequest $request, array $allowedStatuses): ExpenseRequest
    {
        $fresh = ExpenseRequest::lockForUpdate()->findOrFail($request->id);

        if (! in_array($fresh->status, $allowedStatuses, true)) {
            throw new BusinessRuleException("مينفعش تعمل كده والطلب حالته \"{$fresh->statusLabel()}\".");
        }

        return $fresh;
    }

    private function releaseRecurringPeriod(ExpenseRequest $request): void
    {
        if (! $request->recurring_expense_period_id) {
            return;
        }

        $period = RecurringExpensePeriod::find($request->recurring_expense_period_id);
        $period?->update([
            'expense_request_id' => null,
            'status' => $period->due_date->isPast() && ! $period->due_date->isToday()
                ? RecurringExpensePeriod::OVERDUE
                : RecurringExpensePeriod::DUE,
        ]);
    }

    private function event(ExpenseRequest $request, string $action, ?User $user, ?string $note = null, ?array $meta = null): void
    {
        $request->events()->create([
            'action' => $action,
            'user_id' => $user?->id,
            'note' => $note,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    private function notifyRequester(ExpenseRequest $request, string $event): void
    {
        $request->loadMissing('requester');
        $request->requester?->notify(new ExpenseRequestNotification($request, $event));
    }
}
