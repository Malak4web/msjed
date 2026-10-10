<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** المرفقات بتتفتح بروابط موقّعة مؤقتة بس (middleware: signed) */
class AttachmentController extends Controller
{
    public function show(Attachment $attachment): Response
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
