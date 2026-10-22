<?php

namespace App\Rules;

use App\Support\SecurityMessage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

class SafeMedia implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(SecurityMessage::get('invalidMedia'));

            return;
        }
        try {
            $path = $value->getRealPath();
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            if (str_starts_with($mime, 'image/')) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/x-ms-bmp', 'image/avif', 'image/heic', 'image/heif', 'image/tiff', 'image/x-tiff'];
                if (! in_array($mime, $allowed, true)) {
                    throw new \RuntimeException;
                }
                $size = @getimagesize($path);
                if (! $size && extension_loaded('imagick')) {
                    \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
                    \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
                    $image = new \Imagick;
                    $image->pingImage($path);
                    $size = [$image->getImageWidth(), $image->getImageHeight()];
                    $image->clear();
                }
                if (! $size || min($size[0], $size[1]) < 1 || max($size[0], $size[1]) > config('media_security.max_dimension') || config('media_security.max_pixels') < $size[0] * $size[1]) {
                    throw new \RuntimeException;
                }
            } elseif (in_array($mime, ['video/mp4', 'application/mp4', 'video/quicktime', 'video/webm', 'video/ogg', 'application/ogg'], true)) {
                $probe = new Process([(string) config('services.ffmpeg.ffprobe_binary', 'ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe', '-show_entries', 'stream=codec_type,width,height:format=duration', '-of', 'json', $path]);
                $probe->setTimeout(10);
                $probe->mustRun();
                $data = json_decode($probe->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $video = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');
                $duration = (float) ($data['format']['duration'] ?? 0);
                if (! $video || $duration <= 0 || $duration > config('media_security.max_video_seconds') || ($video['width'] ?? 0) < 1 || ($video['height'] ?? 0) < 1 || config('media_security.max_pixels') < $video['width'] * $video['height']) {
                    throw new \RuntimeException;
                }
            } else {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            $fail(SecurityMessage::get('invalidMedia'));
        }
    }
}
