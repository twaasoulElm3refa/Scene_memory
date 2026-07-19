<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\EventsImges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    public function downloads(Request $request): JsonResponse
    {
        $media = EventsImges::query()
            ->select(['events_imges.id', 'events_imges.preview_url', 'events_imges.type', 'events_imges.width', 'events_imges.height'])
            ->join('entitlements', 'entitlements.media_id', '=', 'events_imges.id')
            ->where('entitlements.user_id', $request->user()->id)
            ->orderByDesc('entitlements.granted_at')
            ->distinct()
            ->get()
            ->map(fn (EventsImges $item) => [
                'id' => $item->id,
                'preview_url' => $item->preview_url,
                'type' => $item->type,
                'width' => $item->width,
                'height' => $item->height,
                'download_url' => route('media.download', ['media' => $item->id], false),
            ]);

        return response()->json(['data' => $media]);
    }

    public function download(Request $request, EventsImges $media): BinaryFileResponse|JsonResponse
    {
        $owned = Entitlement::query()
            ->where('user_id', $request->user()->id)
            ->where('media_id', $media->id)
            ->exists();
        if (! $owned) {
            return response()->json(['message' => 'Download is not authorized.'], 403);
        }

        $path = $this->safeRelativePath($media->full_url);
        if ($path === null) {
            return response()->json(['message' => 'Media file is unavailable.'], 404);
        }

        if (Storage::disk('public')->exists($path)) {
            $file = Storage::disk('public')->path($path);
        } elseif (is_file(public_path($path))) {
            $file = public_path($path);
        } else {
            return response()->json(['message' => 'Media file is unavailable.'], 404);
        }

        Log::info('download_authorized', ['user_id' => $request->user()->id, 'media_id' => $media->id]);
        return response()->download($file, basename($file), ['Cache-Control' => 'private, no-store']);
    }

    private function safeRelativePath(?string $value): ?string
    {
        if (! $value || parse_url($value, PHP_URL_SCHEME) !== null) {
            return null;
        }
        $path = str_replace('\\', '/', trim($value));
        $path = preg_replace('#^(?:/)?(?:storage|public)/#', '', $path);
        $path = ltrim((string) $path, '/');

        return $path !== '' && ! str_contains($path, '..') ? $path : null;
    }
}
