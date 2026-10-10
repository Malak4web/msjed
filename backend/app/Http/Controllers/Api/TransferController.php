<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private TransferService $transfers) {}

    public function index(Request $request): JsonResponse
    {
        $page = Transfer::with('from:id,name', 'to:id,name', 'creator:id,name', 'reverser:id,name')
            ->when($request->integer('custodian_id'), fn ($q, $v) => $q->where(fn ($w) => $w->where('from_custodian_id', $v)->orWhere('to_custodian_id', $v)))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Transfer $t) => $this->present($t)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_custodian_id' => ['required', 'exists:custodians,id'],
            'to_custodian_id' => ['required', 'exists:custodians,id', 'different:from_custodian_id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'to_custodian_id.different' => 'مينفعش تسلّم فلوس لنفس الشخص.',
            'amount.gt' => 'اكتب مبلغ أكبر من صفر.',
        ]);

        $transfer = $this->transfers->create($data, $request->user());
        $transfer->load('from', 'to', 'creator');

        return response()->json([
            'message' => "تم تسليم الفلوس من {$transfer->from->name} إلى {$transfer->to->name}",
            'data' => $this->present($transfer),
        ], 201);
    }

    public function reverse(ReasonRequest $request, Transfer $transfer): JsonResponse
    {
        $transfer = $this->transfers->reverse($transfer, $request->validated('reason'), $request->user());

        return response()->json(['message' => 'تم إلغاء التسليم بقيد عكسي', 'data' => $this->present($transfer->load('from', 'to', 'creator', 'reverser'))]);
    }

    private function present(Transfer $t): array
    {
        return [
            'id' => $t->id,
            'number' => $t->number,
            'date' => $t->date->toDateString(),
            'amount' => $t->amount,
            'from' => ['id' => $t->from->id, 'name' => $t->from->name],
            'to' => ['id' => $t->to->id, 'name' => $t->to->name],
            'notes' => $t->notes,
            'created_by' => $t->creator?->name,
            'created_at' => $t->created_at,
            'is_reversed' => $t->isReversed(),
            'reversal_reason' => $t->reversal_reason,
        ];
    }
}
