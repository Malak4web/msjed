<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Income */
class IncomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'date' => $this->date->toDateString(),
            'amount' => $this->amount,
            'source' => $this->whenLoaded('source', fn () => ['id' => $this->source->id, 'name' => $this->source->name, 'icon' => $this->source->icon]),
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => ['id' => $this->paymentMethod->id, 'name' => $this->paymentMethod->name, 'icon' => $this->paymentMethod->icon]),
            'custodian' => $this->whenLoaded('custodian', fn () => ['id' => $this->custodian->id, 'name' => $this->custodian->name]),
            'payer_name' => $this->payer_name,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator->name),
            'created_at' => $this->created_at,
            'is_reversed' => $this->isReversed(),
            'reversed_at' => $this->reversed_at,
            'reversal_reason' => $this->reversal_reason,
            'reversed_by' => $this->whenLoaded('reverser', fn () => $this->reverser?->name),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
