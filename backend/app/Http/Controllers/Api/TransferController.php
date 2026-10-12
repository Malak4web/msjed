<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private TransferService $transfers) {}

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $page = Transfer::with([
            'from:id,name',
            'to:id,name',
            'creator:id,name',
            'reverser:id,name',
            'receiver:id,name',
            'rejecter:id,name',
            'canceller:id,name',
        ])
            ->when($request->integer('custodian_id'), fn ($q, $v) => $q->where(fn ($w) => $w->where('from_custodian_id', $v)->orWhere('to_custodian_id', $v)))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Transfer $t) => $this->present($t, $viewer)),
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
            'message' => "تم إرسال أمر تسليم الفلوس وبانتظار تأكيد استلام {$transfer->to->name}",
            'data' => $this->present($transfer, $request->user()),
        ], 201);
    }

    public function accept(Request $request, Transfer $transfer): JsonResponse
    {
        $transfer = $this->transfers->accept($transfer, $request->user());
        $transfer->load('from', 'to', 'creator', 'receiver');

        return response()->json([
            'message' => "تم تأكيد استلام العهدة وإضافتها لرصيد {$transfer->to->name}",
            'data' => $this->present($transfer, $request->user()),
        ]);
    }

    public function reject(Request $request, Transfer $transfer): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $transfer = $this->transfers->reject($transfer, $data['reason'] ?? null, $request->user());
        $transfer->load('from', 'to', 'creator', 'rejecter');

        return response()->json([
            'message' => 'تم رفض استلام العهدة وإلغاء حجز المبلغ',
            'data' => $this->present($transfer, $request->user()),
        ]);
    }

    public function cancel(Request $request, Transfer $transfer): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $transfer = $this->transfers->cancel($transfer, $data['reason'] ?? null, $request->user());
        $transfer->load('from', 'to', 'creator', 'canceller');

        return response()->json([
            'message' => 'تم إلغاء أمر تسليم العهدة وإلغاء الحجز',
            'data' => $this->present($transfer, $request->user()),
        ]);
    }

    public function reverse(ReasonRequest $request, Transfer $transfer): JsonResponse
    {
        $transfer = $this->transfers->reverse($transfer, $request->validated('reason'), $request->user());
        $transfer->load('from', 'to', 'creator', 'reverser');

        return response()->json([
            'message' => 'تم إلغاء التسليم بقيد عكسي',
            'data' => $this->present($transfer, $request->user()),
        ]);
    }

    private function present(Transfer $t, ?User $viewer = null): array
    {
        $canAccept = false;
        $canReject = false;
        $canCancel = false;

        if ($viewer && $t->isPending()) {
            $isRecipient = $viewer->custodian_id && (int) $viewer->custodian_id === (int) $t->to_custodian_id;
            $isSender = $viewer->custodian_id && (int) $viewer->custodian_id === (int) $t->from_custodian_id;
            $isCreator = (int) $viewer->id === (int) $t->created_by;
            $isImam = $viewer->isImam();

            $canAccept = $isRecipient || $isImam;
            $canReject = $isRecipient || $isImam;
            $canCancel = $isCreator || $isSender || $isImam;
        }

        return [
            'id' => $t->id,
            'number' => $t->number,
            'date' => $t->date->toDateString(),
            'amount' => $t->amount,
            'status' => $t->status,
            'status_label' => Transfer::STATUS_LABELS[$t->status] ?? $t->status,
            'from' => ['id' => $t->from->id, 'name' => $t->from->name],
            'to' => ['id' => $t->to->id, 'name' => $t->to->name],
            'notes' => $t->notes,
            'created_by' => $t->creator?->name,
            'created_at' => $t->created_at,
            'received_at' => $t->received_at,
            'received_by' => $t->receiver?->name,
            'rejected_at' => $t->rejected_at,
            'rejected_by' => $t->rejecter?->name,
            'rejection_reason' => $t->rejection_reason,
            'cancelled_at' => $t->cancelled_at,
            'cancelled_by' => $t->canceller?->name,
            'cancellation_reason' => $t->cancellation_reason,
            'is_reversed' => $t->isReversed(),
            'reversed_at' => $t->reversed_at,
            'reversed_by' => $t->reverser?->name,
            'reversal_reason' => $t->reversal_reason,
            'can_accept' => $canAccept,
            'can_reject' => $canReject,
            'can_cancel' => $canCancel,
        ];
    }
}
