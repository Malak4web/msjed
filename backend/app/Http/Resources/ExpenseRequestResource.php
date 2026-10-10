<?php

namespace App\Http\Resources;

use App\Models\ExpenseRequestEvent;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ExpenseRequest */
class ExpenseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $detailed = $this->relationLoaded('events');

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'type' => $this->type,
            'amount' => $this->amount,
            'actual_amount' => $this->actual_amount,
            'description' => $this->description,
            'custodian' => $this->whenLoaded('custodian', fn () => [
                'id' => $this->custodian->id,
                'name' => $this->custodian->name,
                'title' => $this->custodian->title,
                'phone' => $this->custodian->phone,
                ...($detailed ? app(LedgerService::class)->summary($this->custodian) : []),
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'role_label' => $this->requester->roleLabel(),
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at,
            'auto_approved' => $this->auto_approved,
            'auto_approval_reason' => $this->auto_approval_reason,
            'rejection_reason' => $this->rejection_reason,
            'return_reason' => $this->return_reason,
            'cancel_reason' => $this->cancel_reason,
            'executed_at' => $this->executed_at,
            'executed_date' => $this->executed_date?->toDateString(),
            'is_reversed' => $this->isReversed(),
            'reversal_reason' => $this->reversal_reason,
            'recurring' => $this->recurring_expense_period_id !== null,
            'created_at' => $this->created_at,
            'attachments_count' => $this->whenCounted('attachments'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'timeline' => $this->whenLoaded('events', fn () => $this->events->map(fn (ExpenseRequestEvent $e) => [
                'action' => $e->action,
                'label' => ExpenseRequestEvent::LABELS[$e->action] ?? $e->action,
                'by' => $e->user?->name,
                'note' => $e->note,
                'meta' => $e->meta,
                'at' => $e->created_at,
            ])),
            'can' => $user ? [
                'decide' => $user->can('decide', $this->resource),
                'update' => $user->can('update', $this->resource),
                'execute' => $user->can('execute', $this->resource),
                'cancel' => $user->can('cancel', $this->resource),
                'reverse' => $user->can('reverse', $this->resource),
            ] : null,
        ];
    }
}
