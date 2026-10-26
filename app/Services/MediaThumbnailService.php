<?php

namespace App\Services;

use App\Models\EventsImges;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Card-size thumbnails, made from exactly the file the preview endpoint serves: the
 * watermarked preview, or the original only where that is already public (free,
 * published media without a preview). A thumbnail therefore never shows more than
 * the preview does. Paths are derived from the source, so re-running is harmless
 * and a new preview gets a new thumbnail. Stored on the private media disk.
 */
class MediaThumbnailService
{
    public function __construct(private readonly MediaAccessService $access) {}

    /** Relative path on the media disk, or null when the item has no image preview. */
    public function path(EventsImges $media): ?string
    {
        $source = $this->source($media);
        if (! $source) {
            return null;
        }

        return 'events/thumbs/'.sha1($source[0].'|'.$source[1]).'.'.$this->extension();
    }

    /** Absolute path of an existing thumbnail, or null when it is not generated yet. */
    public function existing(EventsImges $media): ?string
    {
        $path = $this->path($media);
        $disk = Storage::disk('media');

        return $path && $disk->exists($path) ? $disk->path($path) : null;
    }

    public function generate(EventsImges $media): bool
    {
        $target = $this->path($media);
        if (! $target) {
            return false;
        }
        $disk = Storage::disk('media');
        if ($disk->exists($target)) {
            return true;
        }

        try {
            $file = $this->sourceFile($media);
        } catch (HttpExceptionInterface) {
            return false;
        }
        $size = @getimagesize($file);
        if (! $size || (int) config('media_security.max_pixels') < $size[0] * $size[1]) {
            Log::warning('media_thumbnail_skipped', ['media_id' => $media->id, 'reason' => $size ? 'too_many_pixels' : 'unreadable']);

            return false;
        }

        try {
            $edge = max(64, (int) config('media_security.thumbnail_max_edge', 1280));
            $quality = min(100, max(40, (int) config('media_security.thumbnail_quality', 82)));
            $image = $this->manager()->read($file)->scaleDown($edge, $edge);
            $encoded = $this->extension() === 'webp' ? $image->toWebp($quality) : $image->toJpeg($quality);
            $disk->put($target, (string) $encoded);
        } catch (Throwable $exception) {
            Log::warning('media_thumbnail_failed', ['media_id' => $media->id, 'message' => $exception->getMessage()]);

            return false;
        }

        return true;
    }

    /**
     * The disk and path the preview endpoint serves for this item.
     *
     * @return array{0: string, 1: string}|null
     */
    private function source(EventsImges $media): ?array
    {
        if ($media->type === 'video' || ! $this->access->previewUrl($media)) {
            return null;
        }
        $original = ! $media->getRawOriginal('preview_url') && ! $this->access->restrictedOriginal($media);
        $path = $this->access->relativePath($media->getRawOriginal($original ? 'full_url' : 'preview_url'));

        return $path ? [$this->access->disk($media, ! $original), $path] : null;
    }

    private function sourceFile(EventsImges $media): string
    {
        if (! $media->getRawOriginal('preview_url') && ! $this->access->restrictedOriginal($media)) {
            return $this->access->file($media);
        }
        $file = $this->access->file($media, true);
        // Same rule as the preview endpoint: never derive from a protected original.
        abort_if($this->access->restrictedOriginal($media) && $file === $this->safeOriginal($media), 404);

        return $file;
    }

    private function safeOriginal(EventsImges $media): ?string
    {
        try {
            return $this->access->file($media);
        } catch (HttpExceptionInterface) {
            return null;
        }
    }

    private function extension(): string
    {
        if (extension_loaded('imagick')) {
            return \Imagick::queryFormats('WEBP') !== [] ? 'webp' : 'jpg';
        }

        return function_exists('imagewebp') ? 'webp' : 'jpg';
    }

    private function manager(): ImageManager
    {
        return extension_loaded('imagick')
            ? new ImageManager(new ImagickDriver, decodeAnimation: false, strip: true)
            : new ImageManager(new GdDriver, decodeAnimation: false, strip: true);
    }
}
