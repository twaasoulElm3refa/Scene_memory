<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\EventsImges;
use App\Services\MediaAccessService;
use App\Support\SecurityMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    public function downloads(Request $request): JsonResponse
    {
        $latestEntitlements = DB::table('entitlements')
            ->select('media_id')
            ->selectRaw('MAX(granted_at) AS latest_granted_at')
            ->where('user_id', $request->user()->id)
            ->groupBy('media_id');

        $media = EventsImges::query()
            ->select('events_images.*')
            ->joinSub($latestEntitlements, 'user_entitlements', function ($join) {
                $join->on('user_entitlements.media_id', '=', 'events_images.id');
            })
            ->orderByDesc('user_entitlements.latest_granted_at')
            ->orderByDesc('events_images.id')
            ->get()
            ->map(fn (EventsImges $item) => [
                'id' => $item->id,
                'preview_url' => app(MediaAccessService::class)->previewUrl($item, true),
                'type' => $item->type,
                'width' => $item->width,
                'height' => $item->height,
                'download_url' => route('media.download', ['media' => $item->id], false),
            ]);

        return response()->json(['data' => $media]);
    }

    public function download(Request $request, EventsImges $media): BinaryFileResponse|JsonResponse
    {
        $access = app(MediaAccessService::class);
        abort_unless($access->canDownload($media, $request->user()), 403, SecurityMessage::get('denied'));
        $file = $access->file($media);
        Log::info('download_authorized', ['user_id' => $request->user()->id, 'media_id' => $media->id]);

        return response()->download($file, basename($file), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
