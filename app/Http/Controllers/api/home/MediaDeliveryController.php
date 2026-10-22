<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Models\EventsImges;
use App\Services\MediaAccessService;
use App\Support\SecurityMessage;
use Illuminate\Http\Request;

class MediaDeliveryController extends Controller
{
    public function preview(Request $request, EventsImges $media, MediaAccessService $access)
    {
        abort_unless($access->isPublished($media) || $request->hasValidSignature() || $access->ownerOrAdmin($media, $request->user('sanctum')), 404, SecurityMessage::get('notFound'));
        abort_unless($access->previewUrl($media), 404, SecurityMessage::get('unavailable'));
        if (! $media->preview_url && ! $access->restrictedOriginal($media)) {
            return response()->file($access->file($media), ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        $file = $access->file($media, true);
        if ($access->restrictedOriginal($media)) {
            abort_if($file === $access->file($media), 404, SecurityMessage::get('unavailable'));
        }

        return response()->file($file, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    // Only a server-issued, expiring grant sent to the configured moderation service.
    public function reviewOriginal(EventsImges $media, MediaAccessService $access)
    {
        return response()->file($access->file($media), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
