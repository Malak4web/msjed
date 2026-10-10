<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * المرفقات بتتخزن على disk خاص (مش public) وبتتشاف بروابط موقّعة.
 * الصور بتتصغّر وتتضغط عشان صور الموبايل تقيلة.
 */
class AttachmentService
{
    private const MAX_DIMENSION = 1600;

    public function store(Model $owner, UploadedFile $file, string $kind, ?int $userId): Attachment
    {
        $dir = 'attachments/'.now()->format('Y/m');
        $mime = $file->getMimeType();
        $isImage = str_starts_with((string) $mime, 'image/') && $mime !== 'image/svg+xml';

        if ($isImage && ($compressed = $this->compress($file))) {
            $path = $dir.'/'.Str::uuid().'.jpg';
            Storage::disk('local')->put($path, $compressed);
            $mime = 'image/jpeg';
            $size = strlen($compressed);
        } else {
            $path = $file->store($dir, 'local');
            $size = $file->getSize();
        }

        return $owner->attachments()->create([
            'kind' => $kind,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => $size,
            'uploaded_by' => $userId,
        ]);
    }

    private function compress(UploadedFile $file): ?string
    {
        try {
            $image = ImageManager::usingDriver(Driver::class)->decodePath($file->getRealPath());
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

            return (string) $image->encode(new JpegEncoder(quality: 75));
        } catch (Throwable) {
            return null;
        }
    }
}
