<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

class Attachment extends Model
{
    protected $fillable = ['kind', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** رابط محمي مؤقت بدل رابط عام */
    public function signedUrl(): string
    {
        return URL::temporarySignedRoute('attachments.show', now()->addMinutes(60), ['attachment' => $this->id], absolute: false);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
