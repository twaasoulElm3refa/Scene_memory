<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\EventsImges;
use App\Services\MediaAccessService;
use App\Support\PrivateFileResponse;
use App\Support\SecurityMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\User;
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
            ->map(function (EventsImges $item) {
                $original = $item->getRawOriginal('full_url');
                $extension = strtolower(pathinfo((string) $original, PATHINFO_EXTENSION));
                $type = strtolower((string) $item->type);
                if (! in_array($type, ['image', 'video'], true)) {
                    $type = match (true) {
                        in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'], true) => 'image',
                        in_array($extension, ['mp4', 'webm', 'mov', 'm4v', 'ogv'], true) => 'video',
                        default => null,
                    };
                }
                $downloadUrl = route('media.download', ['media' => $item->id], false);
                $originalUrl = $original ? URL::temporarySignedRoute('media.purchased-original', now()->addMinutes(5), [
                    'media' => $item->id,
                    'user' => request()->user()->id,
                ]) : null;

                return [
                    'id' => $item->id,
                    'preview_url' => app(MediaAccessService::class)->previewUrl($item, true),
                    // This authenticated endpoint serves the original; the private storage path is never serialized.
                    'full_url' => $originalUrl,
                    'download_url' => $downloadUrl,
                    'filename' => basename((string) $original),
                    'type' => $type,
                    'width' => $item->width,
                    'height' => $item->height,
                    'price' => $item->price,
                ];
            });

        return response()->json(['data' => $media])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, EventsImges $media): BinaryFileResponse|JsonResponse
    {
        $access = app(MediaAccessService::class);
        abort_unless($access->canDownload($media, $request->user()), 403, SecurityMessage::get('denied'));
        $file = $access->file($media);
        Log::info('download_authorized', ['user_id' => $request->user()->id, 'media_id' => $media->id]);

        return PrivateFileResponse::make($file, basename($file));
    }

    public function purchasedOriginal(Request $request, EventsImges $media, MediaAccessService $access): BinaryFileResponse
    {
        $user = User::find($request->query('user'));
        abort_unless($access->canDownload($media, $user), 403, SecurityMessage::get('denied'));

        return PrivateFileResponse::make($access->file($media));
    }
}
