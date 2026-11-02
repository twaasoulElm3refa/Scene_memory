<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MediaUploadLimit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) return;
        $mime = (string) $value->getMimeType();
        $type = str_starts_with($mime, 'video/') || in_array($mime, ['application/mp4', 'application/ogg'], true) ? 'video' : 'image';
        if (($value->getSize() ?: 0) > (int) config("cloudinary_media.max_{$type}_mb") * 1048576) {
            $fail('The uploaded media exceeds the configured size limit.');
        }
    }
}
