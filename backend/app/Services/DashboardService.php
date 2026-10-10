<?php

namespace App\Services;

use App\Models\ExpenseRequest;
use App\Models\Income;
use App\Models\LedgerEntry;
use App\Models\RecurringExpensePeriod;
use App\Support\Money;
use Illuminate\Support\Carbon;

class DashboardService
{
    /** الإيرادات مقابل المصروفات (المنفّذة فعلًا) لآخر n شهور — بدون الملغي بقيد عكسي */
    public function monthlyChart(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonthsNoOverflow($months - 1);

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $buckets[$start->copy()->addMonthsNoOverflow($i)->format('Y-m')] = ['income' => 0, 'expense' => 0];
        }

        Income::notReversed()->whereDate('date', '>=', $start)->get(['date', 'amount'])
            ->each(function (Income $i) use (&$buckets) {
                $buckets[$i->date->format('Y-m')]['income'] += Money::cents($i->amount);
            });

        ExpenseRequest::notReversed()->where('status', ExpenseRequest::EXECUTED)->whereDate('executed_date', '>=', $start)
            ->get(['executed_date', 'actual_amount'])
            ->each(function (ExpenseRequest $e) use (&$buckets) {
                $buckets[$e->executed_date->format('Y-m')]['expense'] += Money::cents($e->actual_amount);
            });

        return collect($buckets)->map(fn ($v, $month) => [
            'month' => $month.'-01',
            'income' => Money::decimal($v['income']),
            'expense' => Money::decimal($v['expense']),
        ])->values()->all();
    }

    /** المصروفات الثابتة المتأخرة أو اللي ميعادها خلال الأيام الجاية */
    public function upcomingRecurring(int $days = 10): array
    {
        return RecurringExpensePeriod::with('recurringExpense.category', 'recurringExpense.responsible', 'expenseRequest')
            ->whereIn('status', [RecurringExpensePeriod::DUE, RecurringExpensePeriod::OVERDUE])
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('due_date')
            ->get()
            ->map(fn (RecurringExpensePeriod $p) => [
                'id' => $p->id,
                'name' => $p->recurringExpense->name,
                'icon' => $p->recurringExpense->category->icon,
                'amount' => $p->recurringExpense->amount,
                'due_date' => $p->due_date->toDateString(),
                'days_left' => (int) now()->startOfDay()->diffInDays($p->due_date, false),
                'status' => $p->status,
                'responsible' => $p->recurringExpense->responsible->name,
                'request' => $p->expenseRequest ? [
                    'id' => $p->expenseRequest->id,
                    'number' => $p->expenseRequest->number,
                    'status' => $p->expenseRequest->status,
                ] : null,
            ])->all();
    }

    public function monthTotals(): array
    {
        $from = now()->startOfMonth();

        return [
            'income' => Money::decimal(Money::cents(Income::notReversed()->whereDate('date', '>=', $from)->sum('amount'))),
            'expense' => Money::decimal(Money::cents(
                ExpenseRequest::notReversed()->where('status', ExpenseRequest::EXECUTED)->whereDate('executed_date', '>=', $from)->sum('actual_amount')
            )),
        ];
    }

    /** آخر الحركات المالية (لشاشة الموبايل الرئيسية) */
    public function recentActivity(int $limit = 6): array
    {
        return LedgerEntry::with(['custodian:id,name', 'related'])
            ->orderByDesc('date')->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (LedgerEntry $e) {
                $related = $e->related;
                $icon = match (true) {
                    $related instanceof Income => $related->source?->icon ?? 'payments',
                    $related instanceof ExpenseRequest => $related->category?->icon ?? 'shopping_cart',
                    $e->type === LedgerEntry::REVERSAL => 'undo',
                    default => 'swap_horiz',
                };
                $title = match (true) {
                    $related instanceof Income => $related->source?->name.($related->payer_name ? " — {$related->payer_name}" : ''),
                    $related instanceof ExpenseRequest => $related->description,
                    default => $e->description,
                };

                return [
                    'id' => $e->id,
                    'type' => $e->type,
                    'type_label' => LedgerEntry::TYPE_LABELS[$e->type] ?? $e->type,
                    'title' => $title,
                    'icon' => $icon,
                    'amount' => $e->amount,
                    'custodian' => $e->custodian?->name,
                    'date' => $e->date->toDateString(),
                    'created_at' => $e->created_at,
                    'request_id' => $related instanceof ExpenseRequest ? $related->id : null,
                ];
            })->all();
    }

    public static function pendingCount(): int
    {
        return ExpenseRequest::where('status', ExpenseRequest::PENDING)->count();
    }
}
