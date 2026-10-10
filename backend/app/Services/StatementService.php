<?php

namespace App\Services;

use App\Models\Custodian;
use App\Models\ExpenseRequest;
use App\Models\Income;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * كشف حساب شخص: رصيد أول المدة + كل حركة بالرصيد بعدها + المصروف حسب البند.
 */
class StatementService
{
    public function build(Custodian $custodian, ?string $from, ?string $to): array
    {
        $from = $from ? Carbon::parse($from)->toDateString() : null;
        $to = Carbon::parse($to ?? now())->toDateString();

        $opening = $from
            ? Money::cents(LedgerEntry::where('custodian_id', $custodian->id)->whereDate('date', '<', $from)->sum('amount'))
            : 0;

        $entries = LedgerEntry::with('related')
            ->where('custodian_id', $custodian->id)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->whereDate('date', '<=', $to)
            ->orderBy('date')->orderBy('id')
            ->get();

        $running = $opening;
        $in = 0;
        $out = 0;
        $rows = [];

        foreach ($entries as $e) {
            $amount = Money::cents($e->amount);
            $running += $amount;
            $amount > 0 ? $in += $amount : $out -= $amount;

            $related = $e->related;
            $invoice = $related instanceof ExpenseRequest
                ? $related->attachments()->where('kind', 'invoice')->first()
                : ($related instanceof Income ? $related->attachments()->first() : null);

            $rows[] = [
                'id' => $e->id,
                'date' => $e->date->toDateString(),
                'description' => $e->description,
                'type' => $e->type,
                'type_label' => LedgerEntry::TYPE_LABELS[$e->type] ?? $e->type,
                'in' => $amount > 0 ? Money::decimal($amount) : null,
                'out' => $amount < 0 ? Money::decimal(-$amount) : null,
                'balance' => Money::decimal($running),
                'reference' => match (true) {
                    $related instanceof Income => $related->receipt_no,
                    $related instanceof ExpenseRequest => $related->number,
                    $related instanceof Transfer => $related->number,
                    default => null,
                },
                'related' => $related ? ['type' => class_basename($related), 'id' => $related->getKey()] : null,
                'invoice_url' => $invoice?->signedUrl(),
                'reversed' => $related && method_exists($related, 'isReversed') && $related->isReversed() && $e->type !== LedgerEntry::REVERSAL,
                'created_at' => $e->created_at,
            ];
        }

        return [
            'custodian' => ['id' => $custodian->id, 'name' => $custodian->name, 'title' => $custodian->title],
            'from' => $from,
            'to' => $to,
            'opening' => Money::decimal($opening),
            'total_in' => Money::decimal($in),
            'total_out' => Money::decimal($out),
            'closing' => Money::decimal($running),
            'current' => app(LedgerService::class)->summary($custodian),
            'by_category' => $this->byCategory($custodian, $from, $to),
            'rows' => $rows,
        ];
    }

    /** المصروف حسب البند (المنفّذ فعلًا وغير الملغي) */
    public function byCategory(Custodian $custodian, ?string $from, string $to): array
    {
        return ExpenseRequest::notReversed()
            ->join('expense_categories', 'expense_categories.id', '=', 'expense_requests.expense_category_id')
            ->where('expense_requests.custodian_id', $custodian->id)
            ->where('expense_requests.status', ExpenseRequest::EXECUTED)
            ->when($from, fn ($q) => $q->whereDate('expense_requests.executed_date', '>=', $from))
            ->whereDate('expense_requests.executed_date', '<=', $to)
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.name as name, SUM(expense_requests.actual_amount) as total, COUNT(*) as count')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'total' => Money::decimal(Money::cents($r->total)), 'count' => (int) $r->count])
            ->all();
    }

    /** نفس الكشف بشكل جدول للتصدير */
    public function table(array $s): array
    {
        $period = $s['from'] ? "من {$s['from']} إلى {$s['to']}" : "حتى {$s['to']}";

        return [
            'title' => "كشف حساب {$s['custodian']['name']}",
            'subtitle' => trim(($s['custodian']['title'] ?? '').' — '.$period, ' —'),
            'summary' => [
                ['label' => 'رصيد أول المدة', 'value' => $s['opening']],
                ['label' => 'إجمالي الوارد', 'value' => $s['total_in']],
                ['label' => 'إجمالي المنصرف', 'value' => $s['total_out']],
                ['label' => 'الرصيد آخر المدة', 'value' => $s['closing']],
            ],
            'columns' => [
                ['key' => 'date', 'label' => 'التاريخ'],
                ['key' => 'description', 'label' => 'البيان'],
                ['key' => 'type_label', 'label' => 'النوع'],
                ['key' => 'reference', 'label' => 'المرجع'],
                ['key' => 'in', 'label' => 'وارد', 'type' => 'money', 'class' => 'in'],
                ['key' => 'out', 'label' => 'منصرف', 'type' => 'money', 'class' => 'out'],
                ['key' => 'balance', 'label' => 'الرصيد بعد الحركة', 'type' => 'money'],
            ],
            'rows' => [
                ['date' => $s['from'] ?? '', 'description' => 'رصيد أول المدة', 'type_label' => '', 'reference' => '', 'in' => null, 'out' => null, 'balance' => $s['opening']],
                ...array_map(fn ($r) => $r + ['_struck' => $r['reversed']], $s['rows']),
            ],
            'totals' => ['in' => $s['total_in'], 'out' => $s['total_out'], 'balance' => $s['closing']],
        ];
    }
}
