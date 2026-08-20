<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateEventAiTagsJob;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EventImageController extends Controller
{
    use ApiResponse;

    private int $cacheTime = 600;

    public function __construct(
        private readonly EventImageRepositoryInterface $eventImageRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {}

    public function allPerEvent()
    {
        $eventId = request('id');
        $cacheKey = "event_image_event_{$eventId}";

        $eventImages = Cache::remember($cacheKey, $this->cacheTime, function () use ($eventId) {
            return $this->eventImageRepository->findByEventId((int) $eventId);
        });

        return $this->success(
            $this->normalizeMediaCollection($eventImages),
            'Event images fetched successfully'
        );
    }

    public function create(Request $request)
    {
        $request->validate([
            'url' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif,mp4,webm,mov', 'max:51200'],
            'video' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:51200'],
        ]);

        try {
            $file = $request->file('url') ?: $request->file('video');

            if (! $file) {
                return $this->error('No media file was uploaded', 422);
            }

            $event = $this->eventRepository->findByIdOrFail((int) request('id'));
            $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
            $path = $file->store($type === 'video' ? 'eventVideos' : 'eventImages', 'public');

            $data = [
                'event_id' => $event->id,
                'preview_url' => $path,
                'full_url' => $path,
                'type' => $type,
                'is_active' => 1,
                'size' => (string) $file->getSize(),
            ];

            if (Schema::hasColumn('events_imges', 'url')) {
                $data['url'] = $path;
            }

            if ($type === 'video' && Schema::hasColumn('events_imges', 'video')) {
                $data['video'] = $path;
            }

            $eventImage = $this->eventImageRepository->create($data);
            $this->clearCache($event->id, $event->slug);
            GenerateEventAiTagsJob::dispatch((int) $event->id);

            return $this->success(
                $this->normalizeMedia($eventImage),
                'Event media added successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete()
    {
        try {
            $eventImage = $this->eventImageRepository->findOrFail((int) request('id'));
            $event = $this->eventRepository->findByIdOrFail((int) $eventImage->event_id);

            $eventImage->delete();
            $this->clearCache($event->id, $event->slug);

            return $this->success($eventImage, 'Event media deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function clearCache($eventId = null, $slug = null): void
    {
        if ($eventId) {
            Cache::forget("event_image_event_{$eventId}");
        }

        if (! $slug) {
            return;
        }

        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr'];

        foreach ($locales as $locale) {
            Cache::forget("events_single_{$slug}_{$locale}");
            $this->forgetEventsCache('event_'.strtolower(trim($slug))."_{$locale}");
        }
    }

    private function normalizeMediaCollection($media)
    {
        return $media->map(fn ($item) => $this->normalizeMedia($item));
    }

    private function normalizeMedia($media)
    {
        $rawUrl = $media->url ?? $media->preview_url ?? $media->full_url ?? $media->video ?? null;

        $media->url = $this->storageUrl($rawUrl);
        $media->preview_url = $this->storageUrl($media->preview_url ?? $rawUrl);
        $media->full_url = $this->storageUrl($media->full_url ?? $rawUrl);
        $media->type = $media->type ?: ($this->isVideoPath($rawUrl) ? 'video' : 'image');

        return $media;
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::url($path);
    }

    private function isVideoPath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'mov', 'webm', 'avi', 'mkv'], true);
    }

    private function forgetEventsCache(string $key): void
    {
        try {
            Cache::tags(['events'])->forget($key);
        } catch (\Throwable) {
            Cache::forget($key);
        }
    }
}
