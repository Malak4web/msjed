<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\ExpenseRequest;
use App\Models\RecurringExpense;
use App\Models\RecurringExpensePeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class RecurringExpenseService
{
    public function __construct(private ExpenseRequestService $requests) {}

    /** يولّد سجل "مستحق" لكل بند ثابت نشط في الشهر (آمن لو اتنادى أكتر من مرة) */
    public function generateForMonth(?CarbonInterface $month = null): int
    {
        $month = Carbon::parse($month ?? now())->startOfMonth();
        $created = 0;

        RecurringExpense::where('is_active', true)->each(function (RecurringExpense $item) use ($month, &$created) {
            // whereDate عشان العمود ممكن يتخزن بوقت (00:00:00) حسب قاعدة البيانات
            $exists = RecurringExpensePeriod::where('recurring_expense_id', $item->id)
                ->whereDate('period', $month->toDateString())
                ->exists();

            if (! $exists) {
                RecurringExpensePeriod::create([
                    'recurring_expense_id' => $item->id,
                    'period' => $month->toDateString(),
                    'due_date' => $month->copy()->day(min($item->due_day, $month->daysInMonth))->toDateString(),
                    'status' => RecurringExpensePeriod::DUE,
                ]);
                $created++;
            }
        });

        return $created;
    }

    /** اللي ميعاده عدّى ومتدفعش يبقى "متأخر" */
    public function markOverdue(): int
    {
        return RecurringExpensePeriod::where('status', RecurringExpensePeriod::DUE)
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => RecurringExpensePeriod::OVERDUE]);
    }

    public function createRequest(RecurringExpensePeriod $period, User $user): ExpenseRequest
    {
        $period->loadMissing('recurringExpense', 'expenseRequest');

        $existing = $period->expenseRequest;
        if ($existing && ! in_array($existing->status, [ExpenseRequest::REJECTED, ExpenseRequest::CANCELLED], true)) {
            throw new BusinessRuleException("اتعمل طلب صرف للبند ده الشهر ده خلاص (رقم {$existing->number}).");
        }

        if ($period->status === RecurringExpensePeriod::PAID) {
            throw new BusinessRuleException('البند ده اتدفع الشهر ده خلاص.');
        }

        $item = $period->recurringExpense;
        $month = $period->period->locale('ar')->translatedFormat('F Y');

        return $this->requests->create([
            'custodian_id' => $item->responsible_custodian_id,
            'expense_category_id' => $item->expense_category_id,
            'amount' => $item->amount,
            'description' => "{$item->name} — شهر {$month}",
        ], $user, [], $period);
    }
}
