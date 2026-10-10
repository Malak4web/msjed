<?php

namespace App\Http\Resources;

use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Custodian */
class CustodianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'has_login' => $this->whenLoaded('user', fn () => $this->user !== null),
            'user_id' => $this->whenLoaded('user', fn () => $this->user?->id),
            // balance / reserved / available
            $this->merge(app(LedgerService::class)->summary($this->resource)),
            'month_in' => $this->whenHas('month_in'),
            'month_out' => $this->whenHas('month_out'),
        ];
    }
}
