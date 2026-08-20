<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Jobs\GenerateEventAiTagsJob;
use App\Jobs\ProcessEventImageJob;
use App\Jobs\ProcessEventVideoJob;
use App\Jobs\ReviewEventRequestWithAi;
use App\Jobs\TranslateEventJob;
use App\Models\Event_Tags;
use App\Models\Tags;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;
use App\Services\EventTagCacheService;
use App\Services\TagResolverService;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventUserCreateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly RequestRepositoryInterface $requestRepository,
        private readonly TagResolverService $tagResolver,
        private readonly EventTagCacheService $cache
    ) {}

    public function create(EventsRequest $request)
    {
        return $this->createEvent($request, false);
    }

    public function historic(EventsRequest $request)
    {
        return $this->createEvent($request, true);
    }

    protected function createEvent(
        EventsRequest $request,
        bool $isHistorical,
        bool $requiresModeration = true,
        bool $isTrending = false
    ) {
        $data = $request->validated();
        $data['is_real'] = $request->has('is_real')
            ? $request->boolean('is_real')
            : false;
        $data['is_historical'] = $isHistorical;
        $this->stripUploadOnlyData($data);
        $imageJobs = [];
        $videoJobs = [];
        $eventRequestId = null;
        try {
            $event = DB::transaction(function () use (
                $data,
                $request,
                $requiresModeration,
                $isTrending,
                &$imageJobs,
                &$videoJobs,
                &$eventRequestId
            ) {
                $data['user_id'] = auth()->id();
                $data['is_active'] = ! $requiresModeration;
                $data['is_trending'] = ! $requiresModeration && $isTrending;
                $event = $this->eventRepository->create($data);
                if ($requiresModeration) {
                    $eventRequest = $this->requestRepository->createEventRequest([
                        'event_id' => $event->id,
                    ]);
                    $eventRequestId = (int) $eventRequest->id;
                }
                $event->update(['slug' => 'event'.'-'.Str::slug($data['title']).$event->id]);
                $event->translations()->create([
                    'locale' => 'ar',
                    'title' => $data['title'],
                    'description' => $data['description'],
                ]);
                $this->syncEventTags($event->id, $request);
                $uploadedFiles = $request->uploadedMediaFiles();
                if (! empty($uploadedFiles)) {
                    foreach ($uploadedFiles as $index => $file) {
                        if (! $file instanceof UploadedFile) {
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
                            $tempPath = $file->store('images_temp', 'public');
                            if (! $tempPath || trim($tempPath) === '') {
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
                            $photoMetadata = $this->photoMetadataForIndex(
                                $request,
                                $index
                            );
                            \Log::info('Preparing ProcessEventImageJob', [
                                'event_id' => $event->id,
                                'temp_path' => $tempPath,
                                'manual_price' => $manualPrice,
                                'file_name' => $file->getClientOriginalName(),
                            ]);
                            $imageJobs[] = new ProcessEventImageJob(
                                $event->id,
                                $tempPath,
                                $manualPrice,
                                $photoMetadata
                            );
                        } elseif (str_starts_with($mime, 'video/')) {
                            try {
                                $path = $file->store('videos_temp', 'public');
                                $videoJobs[] = new ProcessEventVideoJob($event->id, $path);
                            } catch (\Throwable $e) {
                                \Log::error('Video processing dispatch failed', [
                                    'name' => $file->getClientOriginalName(),
                                    'mime' => $mime,
                                    'message' => $e->getMessage(),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
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

                if ($imageJobs === [] && $videoJobs === []) {
                    throw new \RuntimeException('The uploaded media could not be stored.');
                }

                return $event;
            });
            $this->dispatchPostCommitJobs(
                $event->id,
                $imageJobs,
                $videoJobs,
                $eventRequestId
            );
            TranslateEventJob::dispatch($event->id, $data['title'], $data['description']);
            $this->clearEventsCache((int) $event->id, $requiresModeration);

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );
        } catch (\Throwable $th) {
            \Log::error('Event create failed', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            return $this->error($th->getMessage());
        }
    }

    private function stripUploadOnlyData(array &$data): void
    {
        foreach ([
            'urls',
            'photos',
            'media',
            'image',
            'tags_id',
            'new_tags',
            'photo_descriptions',
            'photo_tags_json',
            'photo_widths',
            'photo_heights',
            'photo_quality_scores',
            'photo_sharpness_scores',
            'photo_blur_scores',
            'photo_validation_statuses',
            'photo_validation_messages',
            'media_prices',
            'media_widths',
            'media_heights',
            'media_quality_scores',
            'media_sharpness_scores',
            'media_contrast_scores',
            'media_brightness_scores',
            'media_file_sizes_mb',
        ] as $key) {
            unset($data[$key]);
        }
    }

    private function photoMetadataForIndex(Request $request, int|string $index): array
    {
        $tags = $this->normalizePhotoTagsPayload($request->input("photo_tags_json.$index"));
        $photoTagIds = $this->resolvePhotoTagIds($tags);
        $validationMessage = $request->input("photo_validation_messages.$index");

        return [
            'description' => trim((string) $request->input("photo_descriptions.$index")),
            'tags_json' => json_encode([
                'tags_id' => $photoTagIds,
                'new_tags' => $tags['new_tags'],
            ]),
            'tag_ids' => $photoTagIds,
            'quality_score' => $request->input("photo_quality_scores.$index"),
            'sharpness_score' => $request->input("photo_sharpness_scores.$index"),
            'blur_score' => $request->input("photo_blur_scores.$index"),
            'megapixels' => null,
            'file_size_mb' => $request->input("media_file_sizes_mb.$index"),
            'validation_status' => $request->input("photo_validation_statuses.$index"),
            'validation_message' => $validationMessage,
        ];
    }

    private function resolvePhotoTagIds(array $tags): array
    {
        $existingTagIds = collect($tags['tags_id'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '' && is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $validExistingTagIds = Tags::query()
            ->whereIn('id', $existingTagIds)
            ->pluck('id');

        $createdTagIds = collect();

        foreach (($tags['new_tags'] ?? []) as $tagName) {
            $tag = $this->tagResolver->resolve(
                is_string($tagName) ? $tagName : null,
                'user'
            );

            if ($tag !== null) {
                $createdTagIds->push($tag->id);
            }
        }

        return $validExistingTagIds
            ->merge($createdTagIds)
            ->unique()
            ->values()
            ->all();
    }

    private function decodePhotoTags(?string $tagsJson): array
    {
        $payload = $this->normalizePhotoTagsPayload($tagsJson);

        return array_merge(
            array_map(fn ($id) => (string) $id, $payload['tags_id']),
            $payload['new_tags']
        );
    }

    private function normalizePhotoTagsPayload(?string $tagsJson): array
    {
        $decoded = json_decode((string) $tagsJson, true);

        if (! is_array($decoded)) {
            return [
                'tags_id' => [],
                'new_tags' => [],
            ];
        }

        if (array_key_exists('tags_id', $decoded) || array_key_exists('new_tags', $decoded)) {
            return [
                'tags_id' => collect($decoded['tags_id'] ?? [])
                    ->filter(fn ($id) => $id !== null && $id !== '' && is_numeric($id))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all(),
                'new_tags' => collect($decoded['new_tags'] ?? [])
                    ->map(fn ($name) => $this->normalizeTagName($name))
                    ->filter()
                    ->unique(fn ($name) => mb_strtolower($name))
                    ->values()
                    ->all(),
            ];
        }

        $existingTagIds = [];
        $newTagNames = [];

        foreach ($decoded as $tag) {
            if (is_array($tag)) {
                $isNew = (bool) ($tag['isNew'] ?? false);
                $name = $this->normalizeTagName($tag['name'] ?? null);
                $id = $tag['id'] ?? null;

                if ($isNew && $name) {
                    $newTagNames[] = $name;

                    continue;
                }

                if (! $isNew && $id !== null && $id !== '' && is_numeric($id)) {
                    $existingTagIds[] = (int) $id;

                    continue;
                }

                if ($name) {
                    $newTagNames[] = $name;
                }

                continue;
            }

            $normalized = $this->normalizeTagName($tag);

            if ($normalized) {
                $newTagNames[] = $normalized;
            }
        }

        return [
            'tags_id' => collect($existingTagIds)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
            'new_tags' => collect($newTagNames)
                ->filter()
                ->unique(fn ($name) => mb_strtolower($name))
                ->values()
                ->all(),
        ];
    }

    private function normalizeTagName(?string $name): ?string
    {
        return $this->tagResolver->normalizeName($name);
    }

    private function syncEventTags(int $eventId, Request $request): void
    {
        $existingTagIds = $request->input('tags_id', []);
        $newTags = $request->input('new_tags', []);

        if (! is_array($existingTagIds)) {
            $existingTagIds = [$existingTagIds];
        }

        if (! is_array($newTags)) {
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

        if (($existingTagIds->count() + $newTagNames->count()) > 10) {
            throw new \Exception('You can select up to 4 tags only');
        }

        $validExistingTagIds = Tags::query()
            ->whereIn('id', $existingTagIds)
            ->pluck('id');

        $createdTagIds = collect();

        foreach ($newTagNames as $tagName) {
            $tag = $this->tagResolver->resolve($tagName, 'user');

            if ($tag !== null) {
                $createdTagIds->push($tag->id);
            }
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
     * @param  array<int, ProcessEventImageJob>  $imageJobs
     * @param  array<int, ProcessEventVideoJob>  $videoJobs
     */
    private function dispatchPostCommitJobs(
        int $eventId,
        array $imageJobs,
        array $videoJobs,
        ?int $eventRequestId
    ): void {
        try {
            $mediaJobs = [...$imageJobs, ...$videoJobs];

            if ($mediaJobs !== []) {
                Bus::batch($mediaJobs)
                    ->name("event-media:{$eventId}")
                    ->allowFailures()
                    ->finally(function (Batch $batch) use ($eventId, $eventRequestId): void {
                        GenerateEventAiTagsJob::dispatch($eventId);

                        if ($eventRequestId !== null) {
                            ReviewEventRequestWithAi::dispatch($eventRequestId);
                        }
                    })
                    ->dispatch();
            } else {
                GenerateEventAiTagsJob::dispatch($eventId);

                if ($eventRequestId !== null) {
                    ReviewEventRequestWithAi::dispatch($eventRequestId);
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Event media batch dispatch failed', [
                'event_id' => $eventId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Clear event-related cache safely using Redis tags
     */
    private function clearEventsCache(?int $eventId = null, bool $clearRequests = true)
    {
        $this->cache->invalidateEvent($eventId);

        if ($clearRequests) {
            $this->cache->invalidateRequests();
        }
    }
}
