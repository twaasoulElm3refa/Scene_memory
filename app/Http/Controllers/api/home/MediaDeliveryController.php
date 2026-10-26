<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\EventsImges;
use App\Services\MediaAccessService;
use App\Services\MediaThumbnailService;
use App\Support\MediaFileResponse;
use App\Support\PrivateFileResponse;
use App\Support\SecurityMessage;
use Illuminate\Http\Request;

class MediaDeliveryController extends Controller
{
    public function preview(Request $request, EventsImges $media, MediaAccessService $access)
    {
        return MediaFileResponse::make($request, $this->previewFile($request, $media, $access), $access->isPublished($media));
    }

    // A card-size copy of the preview under the same access rules. Until it exists the
    // preview itself is served, so the URL always works.
    public function thumbnail(Request $request, EventsImges $media, MediaAccessService $access, MediaThumbnailService $thumbnails)
    {
        $preview = $this->previewFile($request, $media, $access);
        $file = $thumbnails->existing($media);
        if (! $file) {
            GenerateMediaThumbnailJob::dispatch($media->id);
            $file = $preview;
        }

        return MediaFileResponse::make($request, $file, $access->isPublished($media));
    }

    // Only a server-issued, expiring grant sent to the configured moderation service.
    public function reviewOriginal(EventsImges $media, MediaAccessService $access)
    {
        return PrivateFileResponse::make($access->file($media));
    }

    private function previewFile(Request $request, EventsImges $media, MediaAccessService $access): string
    {
        abort_unless($access->isPublished($media) || $request->hasValidSignature() || $access->ownerOrAdmin($media, $request->user('sanctum')), 404, SecurityMessage::get('notFound'));
        abort_unless($access->previewUrl($media), 404, SecurityMessage::get('unavailable'));
        if (! $media->preview_url && ! $access->restrictedOriginal($media)) {
            return $access->file($media);
        }
        $file = $access->file($media, true);
        if ($access->restrictedOriginal($media)) {
            abort_if($file === $access->file($media), 404, SecurityMessage::get('unavailable'));
        }

        return $file;
    }
}
