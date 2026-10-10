<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Attachment */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->original_name,
            'mime' => $this->mime,
            'is_image' => $this->isImage(),
            'size' => $this->size,
            'url' => $this->signedUrl(),
            'created_at' => $this->created_at,
        ];
    }
}
