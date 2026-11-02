<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\EventsImges;
use App\Services\MediaAccessService;
use App\Services\UnifiedMediaStorage;
use App\Support\PrivateFileResponse;
use App\Support\SecurityMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

                $itemData = [
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
                $poster = $type === 'video' ? ($item->toArray()['poster_url'] ?? null) : null;
                if ($poster) $itemData['poster_url'] = $poster;
                return $itemData;
            });

        return response()->json(['data' => $media])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, EventsImges $media): BinaryFileResponse|JsonResponse|Response
    {
        $access = app(MediaAccessService::class);
        abort_unless($access->canDownload($media, $request->user()), 403, SecurityMessage::get('denied'));
        Log::info('media_download_authorized', ['user_id' => $request->user()->id, 'media_id' => $media->id]);
        if ($access->disk($media) === 'cloudinary') return $this->cloudinaryOriginal($media, $access, true);
        $file = $access->file($media);

        return PrivateFileResponse::make($file, basename($file));
    }

    public function purchasedOriginal(Request $request, EventsImges $media, MediaAccessService $access): BinaryFileResponse|Response
    {
        $user = User::find($request->query('user'));
        abort_unless($access->canDownload($media, $user), 403, SecurityMessage::get('denied'));

        if ($access->disk($media) === 'cloudinary') return $this->cloudinaryOriginal($media, $access, false);
        return PrivateFileResponse::make($access->file($media));
    }

    private function cloudinaryOriginal(EventsImges $media, MediaAccessService $access, bool $attachment): BinaryFileResponse|Response
    {
        $asset = $media->cloudinaryOriginalAsset()->where('path', $media->getRawOriginal('full_url'))->first();
        abort_unless($asset && $asset->status === 'ready', 404, SecurityMessage::get('unavailable'));
        try {
            $bytes = app(UnifiedMediaStorage::class)->authenticatedBytes($asset);
            $mime = match ($asset->format) {
                'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
                'gif' => 'image/gif', 'avif' => 'image/avif', 'tif', 'tiff' => 'image/tiff',
                default => 'application/octet-stream',
            };
            $filename = 'scemory-image-'.$media->id.'.'.$asset->format;
            return response($bytes, 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => ($attachment ? 'attachment' : 'inline').'; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $e) {
            Log::warning('media_cloudinary_download_failed', ['media_id' => $media->id, 'exception_category' => get_class($e)]);
            $retained = $access->retainedOriginalFile($media);
            if ($retained) return PrivateFileResponse::make($retained, $attachment ? basename($retained) : null);
            abort(503, SecurityMessage::get('unavailable'));
        }
    }
}
