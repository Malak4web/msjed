<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'role' => $this->roleName(),
            'role_label' => $this->roleLabel(),
            'custodian_id' => $this->custodian_id,
            'custodian' => $this->whenLoaded('custodian', fn () => $this->custodian ? ['id' => $this->custodian->id, 'name' => $this->custodian->name] : null),
            'last_login_at' => $this->last_login_at,
        ];
    }
}
