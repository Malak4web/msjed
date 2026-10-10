<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Custodian;
use App\Models\ExpenseRequest;
use App\Models\ExpenseRequestEvent;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * كل تقرير بيرجع جدول موحّد: title / subtitle / summary / columns / rows / totals (+ chart اختياري)
 * عشان نفس البيانات تتعرض في الشاشة وتتصدّر PDF و Excel.
 */
class ReportService
{
    public const TYPES = [
        'monthly' => 'التقرير الشهري للإيرادات والمصروفات',
        'expenses-by-category' => 'المصروفات حسب البند',
        'incomes-by-source' => 'الإيرادات حسب المصدر وطريقة الدفع',
        'balances' => 'أرصدة الأشخاص',
        'approvals' => 'سجل الموافقات والرفض',
        'audit' => 'سجل التعديلات',
    ];

    /** أسماء الحقول بالعربي في سجل التعديلات */
    private const FIELD_LABELS = [
        'name' => 'الاسم', 'phone' => 'الموبايل', 'email' => 'البريد', 'title' => 'الصفة', 'is_active' => 'نشط',
        'custodian_id' => 'الشخص', 'amount' => 'المبلغ', 'actual_amount' => 'المبلغ الفعلي', 'date' => 'التاريخ',
        'description' => 'الوصف', 'notes' => 'ملاحظات', 'status' => 'الحالة', 'receipt_no' => 'رقم الإيصال',
        'number' => 'الرقم', 'income_source_id' => 'المصدر', 'payment_method_id' => 'طريقة الدفع',
        'expense_category_id' => 'البند', 'type' => 'النوع', 'payer_name' => 'اسم الدافع',
        'requested_by' => 'طلبه', 'approved_by' => 'وافق عليه', 'approved_at' => 'وقت الموافقة',
        'auto_approved' => 'موافقة تلقائية', 'auto_approval_reason' => 'سبب الموافقة التلقائية',
        'rejection_reason' => 'سبب الرفض', 'return_reason' => 'سبب الإرجاع', 'cancel_reason' => 'سبب الإلغاء',
        'executed_at' => 'وقت التنفيذ', 'executed_date' => 'تاريخ التنفيذ', 'executed_by' => 'نفّذه',
        'reversed_at' => 'وقت القيد العكسي', 'reversed_by' => 'عمل القيد العكسي', 'reversal_reason' => 'سبب القيد العكسي',
        'from_custodian_id' => 'من', 'to_custodian_id' => 'إلى', 'created_by' => 'سجّله',
        'due_day' => 'يوم الاستحقاق', 'responsible_custodian_id' => 'المسؤول', 'pre_approved' => 'معتمد مسبقًا',
        'recurring_expense_period_id' => 'شهر البند الثابت', 'period' => 'الشهر', 'locked_by' => 'قفله',
        'key' => 'الإعداد', 'value' => 'القيمة', 'icon' => 'الأيقونة', 'sort' => 'الترتيب', 'id' => 'رقم',
    ];

    private function auditValue(string $key, mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'نعم' : 'لا',
            $key === 'status' && isset(ExpenseRequest::STATUS_LABELS[$value]) => ExpenseRequest::STATUS_LABELS[$value],
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    private string $from;
    private string $to;

    public function build(string $type, ?string $from, ?string $to): array
    {
        if (! isset(self::TYPES[$type])) {
            throw new BusinessRuleException('التقرير ده مش موجود.', 404);
        }

        $this->from = Carbon::parse($from ?? now()->startOfMonth())->toDateString();
        $this->to = Carbon::parse($to ?? now())->toDateString();

        if ($this->from > $this->to) {
            throw new BusinessRuleException('تاريخ البداية لازم يكون قبل تاريخ النهاية.');
        }

        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $type))));

        return [
            'type' => $type,
            'title' => self::TYPES[$type],
            'subtitle' => "من {$this->from} إلى {$this->to}",
            'from' => $this->from,
            'to' => $this->to,
            ...$this->{$method}(),
        ];
    }

    private function monthly(): array
    {
        $start = Carbon::parse($this->from)->startOfMonth();
        $end = Carbon::parse($this->to)->startOfMonth();
        $months = [];
        for ($m = $start->copy(); $m <= $end; $m->addMonthNoOverflow()) {
            $months[$m->format('Y-m')] = ['income' => 0, 'expense' => 0, 'count_in' => 0, 'count_out' => 0];
        }

        Income::notReversed()->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $this->to)->get(['date', 'amount'])
            ->each(function ($i) use (&$months) {
                $k = $i->date->format('Y-m');
                $months[$k]['income'] += Money::cents($i->amount);
                $months[$k]['count_in']++;
            });

        $this->executed()->get(['executed_date', 'actual_amount'])
            ->each(function ($e) use (&$months) {
                $k = $e->executed_date->format('Y-m');
                $months[$k]['expense'] += Money::cents($e->actual_amount);
                $months[$k]['count_out']++;
            });

        $rows = [];
        foreach ($months as $key => $v) {
            $rows[] = [
                'month_key' => $key,
                'month' => Carbon::parse($key.'-01')->locale('ar')->translatedFormat('F Y'),
                'count_in' => $v['count_in'],
                'income' => Money::decimal($v['income']),
                'count_out' => $v['count_out'],
                'expense' => Money::decimal($v['expense']),
                'net' => Money::decimal($v['income'] - $v['expense']),
            ];
        }

        $in = array_sum(array_column($months, 'income'));
        $out = array_sum(array_column($months, 'expense'));

        return [
            'summary' => [
                ['label' => 'إجمالي الإيرادات', 'value' => Money::decimal($in)],
                ['label' => 'إجمالي المصروفات', 'value' => Money::decimal($out)],
                ['label' => 'الصافي (فائض/عجز)', 'value' => Money::decimal($in - $out)],
            ],
            'columns' => [
                ['key' => 'month', 'label' => 'الشهر'],
                ['key' => 'count_in', 'label' => 'عدد الإيصالات', 'type' => 'number'],
                ['key' => 'income', 'label' => 'الإيرادات', 'type' => 'money', 'class' => 'in'],
                ['key' => 'count_out', 'label' => 'عدد المصروفات', 'type' => 'number'],
                ['key' => 'expense', 'label' => 'المصروفات', 'type' => 'money', 'class' => 'out'],
                ['key' => 'net', 'label' => 'الصافي', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                'count_in' => array_sum(array_column($months, 'count_in')),
                'income' => Money::decimal($in),
                'count_out' => array_sum(array_column($months, 'count_out')),
                'expense' => Money::decimal($out),
                'net' => Money::decimal($in - $out),
            ],
            'chart' => ['kind' => 'bar', 'label' => 'month', 'series' => [['key' => 'income', 'label' => 'الإيرادات'], ['key' => 'expense', 'label' => 'المصروفات']]],
        ];
    }

    private function expensesByCategory(): array
    {
        $groups = $this->executed()
            ->join('expense_categories', 'expense_categories.id', '=', 'expense_requests.expense_category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.type')
            ->selectRaw('expense_categories.name as name, expense_categories.type as type, COUNT(*) as count, SUM(expense_requests.actual_amount) as total')
            ->orderByDesc('total')
            ->get();

        $total = $groups->sum(fn ($g) => Money::cents($g->total));

        return [
            'summary' => [
                ['label' => 'إجمالي المصروفات', 'value' => Money::decimal($total)],
                ['label' => 'ثابتة', 'value' => Money::decimal($groups->where('type', 'fixed')->sum(fn ($g) => Money::cents($g->total)))],
                ['label' => 'غير ثابتة', 'value' => Money::decimal($groups->where('type', 'variable')->sum(fn ($g) => Money::cents($g->total)))],
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'البند'],
                ['key' => 'type_label', 'label' => 'النوع'],
                ['key' => 'count', 'label' => 'عدد العمليات', 'type' => 'number'],
                ['key' => 'total', 'label' => 'الإجمالي', 'type' => 'money', 'class' => 'out'],
                ['key' => 'percent', 'label' => 'النسبة %'],
            ],
            'rows' => $groups->map(fn ($g) => [
                'name' => $g->name,
                'type_label' => $g->type === 'fixed' ? 'ثابت' : 'غير ثابت',
                'count' => (int) $g->count,
                'total' => Money::decimal(Money::cents($g->total)),
                'percent' => $total ? round(Money::cents($g->total) * 100 / $total, 1).'%' : '0%',
            ])->values()->all(),
            'totals' => ['count' => (int) $groups->sum('count'), 'total' => Money::decimal($total)],
            'chart' => ['kind' => 'doughnut', 'label' => 'name', 'series' => [['key' => 'total', 'label' => 'الإجمالي']]],
        ];
    }

    private function incomesBySource(): array
    {
        $methods = PaymentMethod::orderBy('sort')->get(['id', 'name']);
        $data = Income::notReversed()->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $this->to)
            ->groupBy('income_source_id', 'payment_method_id')
            ->selectRaw('income_source_id, payment_method_id, SUM(amount) as total')
            ->get();

        $rows = IncomeSource::orderBy('sort')->get(['id', 'name'])->map(function ($s) use ($methods, $data) {
            $row = ['name' => $s->name];
            $sum = 0;
            foreach ($methods as $m) {
                $v = Money::cents($data->first(fn ($d) => $d->income_source_id == $s->id && $d->payment_method_id == $m->id)?->total);
                $row['m'.$m->id] = Money::decimal($v);
                $sum += $v;
            }
            $row['total'] = Money::decimal($sum);
            $row['_sum'] = $sum;

            return $row;
        })->filter(fn ($r) => $r['_sum'] > 0)->values();

        $totals = ['total' => Money::decimal($rows->sum('_sum'))];
        foreach ($methods as $m) {
            $totals['m'.$m->id] = Money::decimal($rows->sum(fn ($r) => Money::cents($r['m'.$m->id])));
        }

        return [
            'summary' => [
                ['label' => 'إجمالي الإيرادات', 'value' => $totals['total']],
                ...$methods->map(fn ($m) => ['label' => $m->name, 'value' => $totals['m'.$m->id]])->filter(fn ($s) => (float) $s['value'] > 0)->values()->all(),
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'المصدر'],
                ...$methods->map(fn ($m) => ['key' => 'm'.$m->id, 'label' => $m->name, 'type' => 'money'])->all(),
                ['key' => 'total', 'label' => 'الإجمالي', 'type' => 'money', 'class' => 'in'],
            ],
            'rows' => $rows->map(fn ($r) => collect($r)->except('_sum')->all())->all(),
            'totals' => $totals,
            'chart' => ['kind' => 'doughnut', 'label' => 'name', 'series' => [['key' => 'total', 'label' => 'الإجمالي']]],
        ];
    }

    private function balances(): array
    {
        $ledger = app(LedgerService::class);
        $rows = Custodian::orderBy('id')->get()->map(function (Custodian $c) use ($ledger) {
            $q = fn () => LedgerEntry::where('custodian_id', $c->id);
            $opening = Money::cents($q()->whereDate('date', '<', $this->from)->sum('amount'));
            $in = Money::cents($q()->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $this->to)->where('amount', '>', 0)->sum('amount'));
            $out = -Money::cents($q()->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $this->to)->where('amount', '<', 0)->sum('amount'));

            return [
                'name' => $c->name,
                'title' => $c->title,
                'opening' => Money::decimal($opening),
                'in' => Money::decimal($in),
                'out' => Money::decimal($out),
                'closing' => Money::decimal($opening + $in - $out),
                'reserved' => Money::decimal($ledger->reservedCents($c->id)),
                '_active' => $c->is_active,
            ];
        })->filter(fn ($r) => $r['_active'] || (float) $r['closing'] != 0)->values();

        $sum = fn ($k) => Money::decimal($rows->sum(fn ($r) => Money::cents($r[$k])));

        return [
            'summary' => [
                ['label' => 'إجمالي فلوس المسجد آخر المدة', 'value' => $sum('closing')],
                ['label' => 'محجوز لطلبات معتمدة (حاليًا)', 'value' => $sum('reserved')],
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'الشخص'],
                ['key' => 'title', 'label' => 'الصفة'],
                ['key' => 'opening', 'label' => 'رصيد أول المدة', 'type' => 'money'],
                ['key' => 'in', 'label' => 'استلم', 'type' => 'money', 'class' => 'in'],
                ['key' => 'out', 'label' => 'صرف/سلّم', 'type' => 'money', 'class' => 'out'],
                ['key' => 'closing', 'label' => 'رصيد آخر المدة', 'type' => 'money'],
                ['key' => 'reserved', 'label' => 'محجوز حاليًا', 'type' => 'money'],
            ],
            'rows' => $rows->map(fn ($r) => collect($r)->except('_active')->all())->all(),
            'totals' => ['opening' => $sum('opening'), 'in' => $sum('in'), 'out' => $sum('out'), 'closing' => $sum('closing'), 'reserved' => $sum('reserved')],
            'chart' => ['kind' => 'doughnut', 'label' => 'name', 'series' => [['key' => 'closing', 'label' => 'الرصيد']]],
        ];
    }

    private function approvals(): array
    {
        $actions = ['approved', 'auto_approved', 'rejected', 'returned', 'cancelled'];
        $events = ExpenseRequestEvent::with('user', 'request.custodian', 'request.category')
            ->whereIn('action', $actions)
            ->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->orderByDesc('created_at')
            ->get();

        $count = fn ($a) => $events->whereIn('action', (array) $a)->count();

        return [
            'summary' => [
                ['label' => 'موافقات', 'value' => $count(['approved', 'auto_approved']), 'type' => 'number'],
                ['label' => 'منها تلقائية', 'value' => $count('auto_approved'), 'type' => 'number'],
                ['label' => 'مرفوض', 'value' => $count('rejected'), 'type' => 'number'],
                ['label' => 'مُرجَع للتعديل', 'value' => $count('returned'), 'type' => 'number'],
            ],
            'columns' => [
                ['key' => 'at', 'label' => 'التاريخ والوقت'],
                ['key' => 'number', 'label' => 'رقم الطلب'],
                ['key' => 'custodian', 'label' => 'من عهدة'],
                ['key' => 'description', 'label' => 'البيان'],
                ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'money'],
                ['key' => 'decision', 'label' => 'القرار'],
                ['key' => 'by', 'label' => 'بواسطة'],
                ['key' => 'note', 'label' => 'السبب/ملاحظة'],
            ],
            'rows' => $events->map(fn (ExpenseRequestEvent $e) => [
                'request_id' => $e->expense_request_id,
                'at' => $e->created_at->format('Y-m-d H:i'),
                'number' => $e->request?->number,
                'custodian' => $e->request?->custodian?->name,
                'description' => $e->request?->description,
                'amount' => $e->request?->amount,
                'action' => $e->action,
                'decision' => ExpenseRequestEvent::LABELS[$e->action] ?? $e->action,
                'by' => $e->user?->name ?? 'النظام',
                'note' => $e->note ?? ($e->action === 'auto_approved' ? $e->request?->auto_approval_reason : null),
            ])->values()->all(),
            'totals' => [],
        ];
    }

    private function audit(): array
    {
        $labels = [
            'Income' => 'إيراد', 'Transfer' => 'تسليم', 'ExpenseRequest' => 'طلب صرف', 'Custodian' => 'شخص',
            'User' => 'مستخدم', 'Setting' => 'إعداد', 'RecurringExpense' => 'مصروف ثابت', 'PeriodLock' => 'إقفال شهر',
            'IncomeSource' => 'مصدر إيراد', 'PaymentMethod' => 'طريقة دفع', 'ExpenseCategory' => 'بند صرف',
        ];
        $events = ['created' => 'إضافة', 'updated' => 'تعديل', 'deleted' => 'حذف'];

        $rows = Activity::with('causer')
            ->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->orderByDesc('id')
            ->limit(2000)
            ->get()
            ->map(function (Activity $a) use ($labels, $events) {
                $changes = collect($a->attribute_changes)->toArray();
                $new = $changes['attributes'] ?? [];
                $old = $changes['old'] ?? [];
                $diff = collect($new)->map(function ($v, $k) use ($old) {
                    $label = self::FIELD_LABELS[$k] ?? $k;

                    return array_key_exists($k, $old)
                        ? "{$label}: ".$this->auditValue($k, $old[$k]).' ← '.$this->auditValue($k, $v)
                        : "{$label}: ".$this->auditValue($k, $v);
                })->take(6)->implode(' | ');

                $subject = $a->subject_type ? ($labels[class_basename($a->subject_type)] ?? class_basename($a->subject_type)) : '';

                return [
                    'at' => $a->created_at->format('Y-m-d H:i'),
                    'user' => $a->causer?->name ?? 'النظام',
                    'event' => $events[$a->event] ?? ($a->event ?: $a->description),
                    'subject' => trim($subject.' '.($a->subject_id ? '#'.$a->subject_id : '')) ?: $a->description,
                    'changes' => $diff ?: (collect($a->properties)->isNotEmpty() ? json_encode(collect($a->properties)->toArray(), JSON_UNESCAPED_UNICODE) : ''),
                ];
            });

        return [
            'summary' => [['label' => 'عدد العمليات المسجلة', 'value' => $rows->count(), 'type' => 'number']],
            'columns' => [
                ['key' => 'at', 'label' => 'التاريخ والوقت'],
                ['key' => 'user', 'label' => 'المستخدم'],
                ['key' => 'event', 'label' => 'العملية'],
                ['key' => 'subject', 'label' => 'على'],
                ['key' => 'changes', 'label' => 'التغييرات (قبل ← بعد)'],
            ],
            'rows' => $rows->all(),
            'totals' => [],
        ];
    }

    private function executed()
    {
        return ExpenseRequest::notReversed()
            ->where('expense_requests.status', ExpenseRequest::EXECUTED)
            ->whereDate('expense_requests.executed_date', '>=', $this->from)
            ->whereDate('expense_requests.executed_date', '<=', $this->to);
    }
}
