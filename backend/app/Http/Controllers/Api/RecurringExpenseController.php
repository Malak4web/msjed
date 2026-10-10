<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseRequestResource;
use App\Models\RecurringExpense;
use App\Models\RecurringExpensePeriod;
use App\Services\RecurringExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RecurringExpenseController extends Controller
{
    public function __construct(private RecurringExpenseService $recurring) {}

    public function index(Request $request): JsonResponse
    {
        $month = Carbon::parse(($request->query('month') ?: now()->format('Y-m')).'-01')->startOfMonth();

        // آمن ويتنادى أكتر من مرة — عشان الشاشة تبقى مظبوطة حتى لو الـ Scheduler مش شغال
        if ($month->lte(now())) {
            $this->recurring->generateForMonth($month);
            $this->recurring->markOverdue();
        }

        $items = RecurringExpense::with([
            'category:id,name,icon,type',
            'responsible:id,name',
            'periods' => fn ($q) => $q->whereDate('period', $month->toDateString())->with('expenseRequest:id,number,status,amount,actual_amount'),
        ])->orderByDesc('is_active')->orderBy('due_day')->get();

        $rows = $items->map(fn (RecurringExpense $r) => $this->present($r, $r->periods->first()));
        $active = $rows->where('is_active', true);

        return response()->json([
            'month' => $month->format('Y-m'),
            'data' => $rows->values(),
            'summary' => [
                'monthly_total' => number_format($active->sum(fn ($r) => (float) $r['amount']), 2, '.', ''),
                'paid' => $active->where('period.status', 'paid')->count(),
                'due' => $active->where('period.status', 'due')->count(),
                'overdue' => $active->where('period.status', 'overdue')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $item = RecurringExpense::create($this->validated($request));
        $this->recurring->generateForMonth();

        return response()->json(['message' => 'تم إضافة المصروف الثابت', 'data' => $this->present($item->load('category', 'responsible'))], 201);
    }

    public function update(Request $request, RecurringExpense $recurringExpense): JsonResponse
    {
        $recurringExpense->update($this->validated($request));

        return response()->json(['message' => 'تم حفظ التعديل', 'data' => $this->present($recurringExpense->load('category', 'responsible'))]);
    }

    /** "إنشاء طلب صرف" جاهز من بيانات البند — بيمشي في نفس دورة موافقة الإمام */
    public function createRequest(Request $request, RecurringExpense $recurringExpense): JsonResponse
    {
        $month = Carbon::parse(($request->input('month') ?: now()->format('Y-m')).'-01');
        $this->recurring->generateForMonth($month);

        $period = RecurringExpensePeriod::where('recurring_expense_id', $recurringExpense->id)
            ->whereDate('period', $month->startOfMonth()->toDateString())
            ->firstOrFail();

        $expense = $this->recurring->createRequest($period, $request->user());

        $message = $expense->auto_approved
            ? "تم إنشاء الطلب {$expense->number} واتوافق عليه تلقائيًا (بند معتمد مسبقًا)"
            : "تم إنشاء الطلب {$expense->number} وإرساله للإمام";

        return response()->json([
            'message' => $message,
            'data' => new ExpenseRequestResource($expense->load('custodian', 'category', 'requester')),
        ], 201);
    }

    private function present(RecurringExpense $r, ?RecurringExpensePeriod $p = null): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'category' => ['id' => $r->category->id, 'name' => $r->category->name, 'icon' => $r->category->icon],
            'amount' => $r->amount,
            'due_day' => $r->due_day,
            'responsible' => ['id' => $r->responsible->id, 'name' => $r->responsible->name],
            'pre_approved' => $r->pre_approved,
            'is_active' => $r->is_active,
            'notes' => $r->notes,
            'period' => $p ? [
                'id' => $p->id,
                'status' => $p->status,
                'due_date' => $p->due_date->toDateString(),
                'request' => $p->expenseRequest ? [
                    'id' => $p->expenseRequest->id,
                    'number' => $p->expenseRequest->number,
                    'status' => $p->expenseRequest->status,
                    'amount' => $p->expenseRequest->actual_amount ?? $p->expenseRequest->amount,
                ] : null,
            ] : null,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'responsible_custodian_id' => ['required', 'exists:custodians,id'],
            'pre_approved' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'due_day.between' => 'يوم الاستحقاق لازم يكون من 1 لـ 28.',
        ]);
    }
}
