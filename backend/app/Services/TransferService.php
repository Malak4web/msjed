<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Custodian;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Models\User;
use App\Notifications\TransferNotification;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TransferService
{
    public function __construct(
        private LedgerService $ledger,
        private SequenceService $sequences,
        private PeriodLockService $periods,
    ) {}

    public function create(array $data, User $user): Transfer
    {
        if ((int) $data['from_custodian_id'] === (int) $data['to_custodian_id']) {
            throw new BusinessRuleException('مينفعش تسلّم فلوس لنفس الشخص.');
        }

        $this->periods->assertOpen($data['date']);

        $transfer = DB::transaction(function () use ($data, $user) {
            $locked = $this->ledger->lockCustodians([$data['from_custodian_id'], $data['to_custodian_id']]);
            $from = $locked[(int) $data['from_custodian_id']];
            $to = $locked[(int) $data['to_custodian_id']];
            $amount = Money::cents($data['amount']);

            $this->ledger->assertAvailable($from, $amount);

            $transfer = Transfer::create([
                'number' => $this->sequences->next('TR', (int) substr($data['date'], 0, 4)),
                'date' => $data['date'],
                'from_custodian_id' => $from->id,
                'to_custodian_id' => $to->id,
                'amount' => Money::decimal($amount),
                'status' => Transfer::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->ledger->refreshCache($from);

            return $transfer;
        });

        // إشعار مستلم العهدة (أو الإمام إذا لم يكن للمستلم حساب)
        $recipients = User::where('custodian_id', $transfer->to_custodian_id)->where('is_active', true)->get();
        if ($recipients->isEmpty()) {
            $recipients = User::role(User::ROLE_IMAM)->where('is_active', true)->get();
        }
        $recipients = $recipients->reject(fn ($u) => $u->id === $user->id);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TransferNotification($transfer, 'pending'));
        }

        return $transfer;
    }

    public function accept(Transfer $transfer, User $user): Transfer
    {
        if (! $transfer->isPending()) {
            throw new BusinessRuleException('التحويل ده مش بانتظار الاستلام.');
        }

        $canAccept = ($user->custodian_id && (int) $user->custodian_id === (int) $transfer->to_custodian_id) || $user->isImam();
        if (! $canAccept) {
            throw new BusinessRuleException('فقط مستلم العهدة (أو إمام المسجد) يقدر يؤكد استلام العهدة.');
        }

        $this->periods->assertOpen($transfer->date->toDateString());

        $updated = DB::transaction(function () use ($transfer, $user) {
            $transfer = Transfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->isPending()) {
                throw new BusinessRuleException('التحويل ده مش بانتظار الاستلام.');
            }

            $locked = $this->ledger->lockCustodians([$transfer->from_custodian_id, $transfer->to_custodian_id]);
            $from = $locked[$transfer->from_custodian_id];
            $to = $locked[$transfer->to_custodian_id];
            $amount = Money::cents($transfer->amount);

            if ($this->ledger->balanceCents($from->id) < $amount) {
                throw new BusinessRuleException("رصيد {$from->name} غير كافي لإتمام التسليم.");
            }

            $this->ledger->post($from, LedgerEntry::TRANSFER_OUT, -$amount, $transfer->date->toDateString(), "تسليم فلوس إلى {$to->name}", $transfer, $user->id);
            $this->ledger->post($to, LedgerEntry::TRANSFER_IN, $amount, $transfer->date->toDateString(), "استلام فلوس من {$from->name}", $transfer, $user->id);

            $transfer->forceFill([
                'status' => Transfer::STATUS_COMPLETED,
                'received_at' => now(),
                'received_by' => $user->id,
            ])->save();

            return $transfer;
        });

        // إشعار لمنشئ التحويل والراسل
        $notifiables = collect([$updated->creator])
            ->merge(User::where('custodian_id', $updated->from_custodian_id)->where('is_active', true)->get())
            ->filter()
            ->unique('id')
            ->reject(fn ($u) => $u->id === $user->id);

        if ($notifiables->isNotEmpty()) {
            Notification::send($notifiables, new TransferNotification($updated, 'accepted'));
        }

        return $updated;
    }

    public function reject(Transfer $transfer, ?string $reason, User $user): Transfer
    {
        if (! $transfer->isPending()) {
            throw new BusinessRuleException('التحويل ده مش بانتظار الاستلام.');
        }

        $canReject = ($user->custodian_id && (int) $user->custodian_id === (int) $transfer->to_custodian_id) || $user->isImam();
        if (! $canReject) {
            throw new BusinessRuleException('فقط مستلم العهدة (أو إمام المسجد) يقدر يرفض استلام العهدة.');
        }

        $updated = DB::transaction(function () use ($transfer, $reason, $user) {
            $transfer = Transfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->isPending()) {
                throw new BusinessRuleException('التحويل ده مش بانتظار الاستلام.');
            }

            $transfer->forceFill([
                'status' => Transfer::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejected_by' => $user->id,
                'rejection_reason' => $reason,
            ])->save();

            $from = Custodian::find($transfer->from_custodian_id);
            if ($from) {
                $this->ledger->refreshCache($from);
            }

            return $transfer;
        });

        $notifiables = collect([$updated->creator])
            ->merge(User::where('custodian_id', $updated->from_custodian_id)->where('is_active', true)->get())
            ->filter()
            ->unique('id')
            ->reject(fn ($u) => $u->id === $user->id);

        if ($notifiables->isNotEmpty()) {
            Notification::send($notifiables, new TransferNotification($updated, 'rejected'));
        }

        return $updated;
    }

    public function cancel(Transfer $transfer, ?string $reason, User $user): Transfer
    {
        if (! $transfer->isPending()) {
            throw new BusinessRuleException('التحويل ده مش بانتظار الاستلام.');
        }

        $canCancel = $user->id === $transfer->created_by ||
            ($user->custodian_id && (int) $user->custodian_id === (int) $transfer->from_custodian_id) ||
            $user->isImam();

        if (! $canCancel) {
            throw new BusinessRuleException('مينفعش تلغي التحويل ده.');
        }

        $updated = DB::transaction(function () use ($transfer, $reason, $user) {
            $transfer = Transfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->isPending()) {
                throw new BusinessRuleException('التحويل ده مش بانتظار الاستلام.');
            }

            $transfer->forceFill([
                'status' => Transfer::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ])->save();

            $from = Custodian::find($transfer->from_custodian_id);
            if ($from) {
                $this->ledger->refreshCache($from);
            }

            return $transfer;
        });

        $notifiables = User::where('custodian_id', $updated->to_custodian_id)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->get();

        if ($notifiables->isNotEmpty()) {
            Notification::send($notifiables, new TransferNotification($updated, 'cancelled'));
        }

        return $updated;
    }

    public function reverse(Transfer $transfer, string $reason, User $user): Transfer
    {
        $today = now()->toDateString();
        $this->periods->assertOpen($today);

        return DB::transaction(function () use ($transfer, $reason, $user, $today) {
            $transfer = Transfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->isCompleted()) {
                throw new BusinessRuleException('مينفعش تعمل قيد عكسي لتحويل لسه مش مكتمل.');
            }

            if ($transfer->isReversed()) {
                throw new BusinessRuleException('التسليم ده اتلغى قبل كده.');
            }

            $locked = $this->ledger->lockCustodians([$transfer->from_custodian_id, $transfer->to_custodian_id]);
            $from = $locked[$transfer->from_custodian_id];
            $to = $locked[$transfer->to_custodian_id];
            $amount = Money::cents($transfer->amount);

            $this->ledger->assertAvailable($to, $amount);

            $this->ledger->post($to, LedgerEntry::REVERSAL, -$amount, $today, "قيد عكسي لتسليم {$transfer->number} — {$reason}", $transfer, $user->id);
            $this->ledger->post($from, LedgerEntry::REVERSAL, $amount, $today, "قيد عكسي لتسليم {$transfer->number} — {$reason}", $transfer, $user->id);

            $transfer->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $user->id,
                'reversal_reason' => $reason,
            ])->save();

            return $transfer;
        });
    }
}
