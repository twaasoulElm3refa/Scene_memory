<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\EventsImges;
use App\Models\User;
use App\Support\SecurityMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class MediaAccessService
{
    public function isPublished(EventsImges $media): bool
    {
        return in_array($media->is_active, [true, 1, '1', 'true', 'active'], true) && (bool) $media->events?->is_active;
    }

    public function ownerOrAdmin(EventsImges $media, ?User $user): bool
    {
        return $user && $user->is_active && ($user->role === 'admin' || (int) $media->events?->user_id === (int) $user->id);
    }

    public function canDownload(EventsImges $media, ?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        return $this->ownerOrAdmin($media, $user) || Entitlement::where('user_id', $user->id)->where('media_id', $media->id)->exists();
    }

    public function restrictedOriginal(EventsImges $media): bool
    {
        return ! $this->isPublished($media) || (float) $media->price > 0;
    }

    public function disk(EventsImges $media, bool $preview = false): string
    {
        $disk = $media->getAttribute($preview ? 'preview_disk' : 'original_disk') ?: 'public';
        abort_unless(in_array($disk, ['public', 'media'], true), 404, SecurityMessage::get('unavailable'));

        return $disk;
    }

    public function relativePath(?string $path): ?string
    {
        if (! $path || str_contains($path, "\0") || preg_match('/[\x00-\x1F\x7F%?#:]/', $path)) {
            return null;
        }
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^/?(?:storage|public)/#', '', $path);
        $path = ltrim($path, '/');
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path ?: null;
    }

    public function file(EventsImges $media, bool $preview = false): string
    {
        $disk = Storage::disk($this->disk($media, $preview));
        $raw = $media->getRawOriginal($preview ? 'preview_url' : 'full_url');
        $path = $this->relativePath($raw);
        abort_unless($path && $disk->exists($path), 404, SecurityMessage::get('unavailable'));
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        abort_unless($root && $file && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR), 404, SecurityMessage::get('unavailable'));

        return $file;
    }

    public function previewUrl(EventsImges $media, bool $review = false): ?string
    {
        $preview = $media->getRawOriginal('preview_url');
        $full = $media->getRawOriginal('full_url');
        if (! $preview) {
            $preview = ! $this->restrictedOriginal($media) ? $full : null;
        }
        // A legacy alias is an original, not a protected derivative.
        if (! $preview || ($this->restrictedOriginal($media) && $this->disk($media, true) === $this->disk($media) && $this->relativePath($preview) === $this->relativePath($full))) {
            return null;
        }
        // Always route event media through a current publication check, including legacy previews.
        if ($review) {
            return URL::temporarySignedRoute('media.preview', now()->addMinutes(5), ['media' => $media->id]);
        }

        return route('media.preview', ['media' => $media->id]);
    }

    /** A card-size copy of the preview, under the same access rules; images only. */
    public function thumbnailUrl(EventsImges $media, bool $review = false): ?string
    {
        if ($media->type === 'video' || ! $this->previewUrl($media)) {
            return null;
        }
        if ($review) {
            return URL::temporarySignedRoute('media.thumbnail', now()->addMinutes(5), ['media' => $media->id]);
        }

        return route('media.thumbnail', ['media' => $media->id]);
    }

    public function reviewOriginalUrl(EventsImges $media): string
    {
        return URL::temporarySignedRoute('media.review-original', now()->addMinutes(5), ['media' => $media->id]);
    }
}
