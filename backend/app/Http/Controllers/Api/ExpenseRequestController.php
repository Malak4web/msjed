<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\StoreExpenseRequestRequest;
use App\Http\Resources\ExpenseRequestResource;
use App\Models\ExpenseRequest;
use App\Services\ExpenseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseRequestController extends Controller
{
    private const DETAIL = ['custodian', 'category', 'requester', 'approver', 'events.user', 'attachments'];

    public function __construct(private ExpenseRequestService $requests) {}

    public function index(Request $request): JsonResponse
    {
        $base = ExpenseRequest::query()
            ->when($request->integer('custodian_id'), fn ($q, $v) => $q->where('custodian_id', $v))
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")));

        $counts = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $page = (clone $base)
            ->when($request->string('status')->value(), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->with('custodian', 'category', 'requester', 'approver')
            ->withCount('attachments')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'returned' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ExpenseRequestResource::collection($page)->additional([
            'counts' => collect(array_keys(ExpenseRequest::STATUS_LABELS))->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
        ])->response();
    }

    public function show(ExpenseRequest $expenseRequest): ExpenseRequestResource
    {
        return new ExpenseRequestResource($expenseRequest->load(self::DETAIL));
    }

    public function store(StoreExpenseRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', ExpenseRequest::class);

        $expense = $this->requests->create($request->validated(), $request->user(), $request->file('attachments', []));

        $message = $expense->status === ExpenseRequest::APPROVED
            ? "تم تسجيل الطلب {$expense->number} واتوافق عليه تلقائيًا ({$expense->auto_approval_reason})"
            : "تم إرسال الطلب {$expense->number} للإمام. هيوصلك إشعار لما يرد.";

        return response()->json(['message' => $message, 'data' => new ExpenseRequestResource($expense->load(self::DETAIL))], 201);
    }

    public function update(StoreExpenseRequestRequest $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        Gate::authorize('update', $expenseRequest);

        $expense = $this->requests->update($expenseRequest, $request->validated(), $request->user(), $request->file('attachments', []));

        return response()->json(['message' => 'تم حفظ التعديل وإرسال الطلب للإمام تاني', 'data' => new ExpenseRequestResource($expense->load(self::DETAIL))]);
    }

    public function approve(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $expense = $this->requests->approve($expenseRequest, $request->user(), $note);

        return $this->respond($expense, 'تمت الموافقة على الطلب — المبلغ اتحجز لحد التنفيذ. بارك الله فيكم.');
    }

    public function reject(ReasonRequest $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $expense = $this->requests->reject($expenseRequest, $request->user(), $request->validated('reason'));

        return $this->respond($expense, 'تم رفض الطلب وإبلاغ صاحبه بالسبب.');
    }

    public function return(ReasonRequest $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $expense = $this->requests->returnForEdit($expenseRequest, $request->user(), $request->validated('reason'));

        return $this->respond($expense, 'الطلب رجع لصاحبه عشان يعدّله.');
    }

    public function execute(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        Gate::authorize('execute', $expenseRequest);

        $data = $request->validate([
            'actual_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'executed_date' => ['required', 'date', 'before_or_equal:today'],
            'invoice' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
        ], [
            'executed_date.before_or_equal' => 'تاريخ التنفيذ مينفعش يكون لسه مجاش.',
        ]);

        $expense = $this->requests->execute($expenseRequest, $request->user(), $data, $request->file('invoice'));

        return $this->respond($expense, 'تم تأكيد التنفيذ وخصم المبلغ من العهدة.');
    }

    public function cancel(ReasonRequest $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        Gate::authorize('cancel', $expenseRequest);
        $expense = $this->requests->cancel($expenseRequest, $request->user(), $request->validated('reason'));

        return $this->respond($expense, 'تم إلغاء الطلب.');
    }

    public function reverse(ReasonRequest $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        Gate::authorize('reverse', $expenseRequest);
        $expense = $this->requests->reverse($expenseRequest, $request->user(), $request->validated('reason'));

        return $this->respond($expense, 'تم إلغاء المصروف بقيد عكسي والمبلغ رجع للعهدة.');
    }

    private function respond(ExpenseRequest $expense, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => new ExpenseRequestResource($expense->fresh()->load(self::DETAIL))]);
    }
}
