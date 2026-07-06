<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Jobs\ProcessEventImageJob;
use App\Jobs\ProcessEventVideoJob;
use App\Jobs\TranslateEventJob;
use App\Models\Event_Tags;
use App\Models\Tags;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventUserCreateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly RequestRepositoryInterface $requestRepository
    ) {
    }

    public function create(EventsRequest $request)
    {
        $data = $request->validated();
        unset($data['urls'], $data['tags_id'], $data['new_tags']);

        try {
            $event = DB::transaction(function () use ($data, $request) {
                // لاحظ: مش محتاجين $imageAnalysisService جوه الـ transaction دلوقتي
                $data['user_id'] = auth()->id();
                $data['is_active'] = 0;

                $event = $this->eventRepository->create($data);


                $this->requestRepository->createEventRequest(['event_id' => $event->id]);
                $event->update(['slug' => 'event' . '-' . Str::slug($data['title']) . $event->id]);


                $event->translations()->create([
                    'locale'      => 'ar',
                    'title'       => $data['title'],
                    'description' => $data['description'],
                ]);

                $this->syncEventTags($event->id, $request);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls', []) as $index => $file) {
                        if (!$file instanceof \Illuminate\Http\UploadedFile) {
                            \Log::error('Invalid uploaded item in urls', ['type' => gettype($file)]);
                            continue;
                        }

                        $mime = (string) $file->getMimeType();

                        $supportedImageMimes = [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'image/bmp',
                            'image/x-ms-bmp',
                            'image/avif',
                            'image/heic',
                            'image/heif',
                            'image/tiff',
                            'image/x-tiff',
                        ];

                        if (in_array($mime, $supportedImageMimes, true)) {
                            // ✅ بس نخزن temp ونـ dispatch — مفيش processing هنا
                            $tempPath = $file->store('images_temp', 'public');

                            if (!$tempPath || trim($tempPath) === '') {
                                \Log::error('Image temp store failed', [
                                    'event_id' => $event->id,
                                    'name' => $file->getClientOriginalName(),
                                    'mime' => $mime,
                                ]);
                                continue;
                            }

                            $manualPrice = $request->input("media_prices.$index");
                            $manualPrice = is_numeric($manualPrice) && (float) $manualPrice > 0
                                ? (float) $manualPrice
                                : 0;

                            \Log::info('Dispatching ProcessEventImageJob', [
                                'event_id' => $event->id,
                                'temp_path' => $tempPath,
                                'manual_price' => $manualPrice,
                                'file_name' => $file->getClientOriginalName(),
                            ]);

                            ProcessEventImageJob::dispatch($event->id, $tempPath, $manualPrice);

                        } elseif (str_starts_with($mime, 'video/')) {
                            try {
                                $path = $file->store('videos_temp', 'public');
                                ProcessEventVideoJob::dispatch($event->id, $path);
                            } catch (\Throwable $e) {
                                \Log::error('Video processing dispatch failed', [
                                    'name'    => $file->getClientOriginalName(),
                                    'mime'    => $mime,
                                    'message' => $e->getMessage(),
                                    'file'    => $e->getFile(),
                                    'line'    => $e->getLine(),
                                ]);
                                throw $e;
                            }
                        } else {
                            \Log::warning('Unsupported upload type skipped', [
                                'name' => $file->getClientOriginalName(),
                                'mime' => $mime,
                            ]);
                        }
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch($event->id, $data['title'], $data['description']);

            $this->clearEventsCache($event->slug);

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );

        } catch (\Throwable $th) {
            \Log::error('Event create failed', [
                'message' => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
                'trace'   => $th->getTraceAsString(),
            ]);

            return $this->error($th->getMessage());
        }
    }

    public function historic(EventsRequest $request)
    {
        $data = $request->validated();
        unset($data['urls'], $data['tags_id'], $data['new_tags']);

        try {
            $event = DB::transaction(function () use ($data, $request) {
                // لاحظ: مش محتاجين $imageAnalysisService جوه الـ transaction دلوقتي
                $data['user_id'] = auth()->id();
                $data['is_active'] = 0;
                $data['is_historical'] = 1;

                $event = $this->eventRepository->create($data);

                $this->requestRepository->createEventRequest(['event_id' => $event->id]);
                $event->update(['slug' => 'event' . '-' . Str::slug($data['title']) .'-'. $event->id]);
                $event->translations()->create([
                    'locale'      => 'ar',
                    'title'       => $data['title'],
                    'description' => $data['description'],
                ]);

                $this->syncEventTags($event->id, $request);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls', []) as $index => $file) {
                        if (!$file instanceof \Illuminate\Http\UploadedFile) {
                            \Log::error('Invalid uploaded item in urls', ['type' => gettype($file)]);
                            continue;
                        }

                        $mime = (string) $file->getMimeType();

                        $supportedImageMimes = [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'image/bmp',
                            'image/x-ms-bmp',
                            'image/avif',
                            'image/heic',
                            'image/heif',
                            'image/tiff',
                            'image/x-tiff',
                        ];

                        if (in_array($mime, $supportedImageMimes, true)) {
                            // ✅ بس نخزن temp ونـ dispatch — مفيش processing هنا
                            $tempPath = $file->store('images_temp', 'public');

                            if (!$tempPath || trim($tempPath) === '') {
                                \Log::error('Image temp store failed', [
                                    'event_id' => $event->id,
                                    'name' => $file->getClientOriginalName(),
                                    'mime' => $mime,
                                ]);
                                continue;
                            }

                            $manualPrice = $request->input("media_prices.$index");
                            $manualPrice = is_numeric($manualPrice) && (float) $manualPrice > 0
                                ? (float) $manualPrice
                                : 0;

                            \Log::info('Dispatching ProcessEventImageJob', [
                                'event_id' => $event->id,
                                'temp_path' => $tempPath,
                                'manual_price' => $manualPrice,
                                'file_name' => $file->getClientOriginalName(),
                            ]);

                            ProcessEventImageJob::dispatch($event->id, $tempPath, $manualPrice);

                        } elseif (str_starts_with($mime, 'video/')) {
                            try {
                                $path = $file->store('videos_temp', 'public');
                                ProcessEventVideoJob::dispatch($event->id, $path);
                            } catch (\Throwable $e) {
                                \Log::error('Video processing dispatch failed', [
                                    'name'    => $file->getClientOriginalName(),
                                    'mime'    => $mime,
                                    'message' => $e->getMessage(),
                                    'file'    => $e->getFile(),
                                    'line'    => $e->getLine(),
                                ]);
                                throw $e;
                            }
                        } else {
                            \Log::warning('Unsupported upload type skipped', [
                                'name' => $file->getClientOriginalName(),
                                'mime' => $mime,
                            ]);
                        }
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch($event->id, $data['title'], $data['description']);

            $this->clearEventsCache($event->slug);

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );

        } catch (\Throwable $th) {
            \Log::error('Event create failed', [
                'message' => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
                'trace'   => $th->getTraceAsString(),
            ]);

            return $this->error($th->getMessage());
        }
    }

    private function normalizeTagName(?string $name): ?string
    {
        $name = trim((string) $name);
        $name = preg_replace('/\s+/u', ' ', $name);

        return $name !== '' ? $name : null;
    }

    private function syncEventTags(int $eventId, Request $request): void
    {
        $existingTagIds = $request->input('tags_id', []);
        $newTags = $request->input('new_tags', []);

        if (!is_array($existingTagIds)) {
            $existingTagIds = [$existingTagIds];
        }

        if (!is_array($newTags)) {
            $newTags = [$newTags];
        }

        $existingTagIds = collect($existingTagIds)
            ->filter(fn ($id) => $id !== null && $id !== '' && is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $newTagNames = collect($newTags)
            ->map(fn ($name) => $this->normalizeTagName($name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();

        if (($existingTagIds->count() + $newTagNames->count()) > 4) {
            throw new \Exception('You can select up to 4 tags only');
        }

        $validExistingTagIds = Tags::query()
            ->whereIn('id', $existingTagIds)
            ->pluck('id');

        $createdTagIds = collect();

        foreach ($newTagNames as $tagName) {
            $slug = Str::slug($tagName);

            if (!$slug) {
                $slug = 'tag-' . md5(mb_strtolower($tagName));
            }

            $tag = Tags::withTrashed()
                ->where('slug', $slug)
                ->first();

            if ($tag) {
                if (method_exists($tag, 'trashed') && $tag->trashed()) {
                    $tag->restore();
                }

                if (!$tag->name) {
                    $tag->update(['name' => $tagName]);
                }
            } else {
                $tag = Tags::create([
                    'name' => $tagName,
                    'slug' => $slug,
                ]);
            }

            $createdTagIds->push($tag->id);
        }

        $allTagIds = $validExistingTagIds
            ->merge($createdTagIds)
            ->unique()
            ->values();

        foreach ($allTagIds as $tagId) {
            $eventTag = Event_Tags::withTrashed()
                ->where('event_id', $eventId)
                ->where('tag_id', $tagId)
                ->first();

            if ($eventTag) {
                if (method_exists($eventTag, 'trashed') && $eventTag->trashed()) {
                    $eventTag->restore();
                }

                continue;
            }

            Event_Tags::create([
                'event_id' => $eventId,
                'tag_id' => $tagId,
            ]);
        }
    }

    /**
     * Clear event-related cache safely using Redis tags
     */
    private function clearEventsCache($slug = null)
    {
        $perPage = 8;

        // Clear paginated caches
        for ($page = 1; $page <= 10; $page++) {
            Cache::tags(['events'])->forget("events_page_{$page}_per_{$perPage}");
        }

        // Clear single event cache
        if ($slug) {
            $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
            foreach ($locales as $locale) {
                Cache::tags(['events'])->forget("events_single_{$slug}_{$locale}");
            }
        }

        // Clear general counts & memories
        Cache::tags(['events'])->forget('events_count');
        Cache::tags(['events'])->forget('memories');
        Cache::tags(['requests'])->flush();
    }
}
