<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Jobs\GenerateEventAiTagsJob;
use App\Jobs\ProcessEventImageJob;
use App\Jobs\ProcessEventVideoJob;
use App\Jobs\TranslateEventJob;
use App\Models\Event_Tags;
use App\Models\Tags;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;
use App\Services\PhotoQualityService;
use App\Services\TagResolverService;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventUserCreateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly RequestRepositoryInterface $requestRepository,
        private readonly PhotoQualityService $photoQualityService,
        private readonly TagResolverService $tagResolver
    ) {}

    public function create(EventsRequest $request)
    {
        $photoValidationResults = $this->validateUserPhotoPayload($request);
        $data = $request->validated();
        // is_real: normalize boolean from FormData
        $data['is_real'] = $request->boolean('is_real');
        $this->stripUploadOnlyData($data);
        $imageJobs = [];
        $videoJobs = [];
        try {
            $event = DB::transaction(function () use (
                $data,
                $request,
                $photoValidationResults,
                &$imageJobs,
                &$videoJobs
            ) {
                $data['user_id'] = auth()->id();
                $data['is_active'] = 0;
                $event = $this->eventRepository->create($data);
                $this->requestRepository->createEventRequest(['event_id' => $event->id]);
                $event->update(['slug' => 'event'.'-'.Str::slug($data['title']).$event->id]);
                $event->translations()->create([
                    'locale' => 'ar',
                    'title' => $data['title'],
                    'description' => $data['description'],
                ]);
                $this->syncEventTags($event->id, $request);
                $uploadedFiles = $this->uploadedMediaFiles($request);
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
                                $index,
                                $photoValidationResults[$index] ?? null
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
                return $event;
            });
            $this->dispatchPostCommitJobs($event->id, $imageJobs, $videoJobs);
            TranslateEventJob::dispatch($event->id, $data['title'], $data['description']);
            $this->clearEventsCache($event->slug);
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

    public function historic(EventsRequest $request)
    {
        $photoValidationResults = $this->validateUserPhotoPayload($request);
        $data = $request->validated();
        $this->stripUploadOnlyData($data);
        $imageJobs = [];
        $videoJobs = [];

        try {
            $event = DB::transaction(function () use (
                $data,
                $request,
                $photoValidationResults,
                &$imageJobs,
                &$videoJobs
            ) {
                // لاحظ: مش محتاجين $imageAnalysisService جوه الـ transaction دلوقتي
                $data['user_id'] = auth()->id();
                $data['is_active'] = 0;
                $data['is_historical'] = 1;

                $event = $this->eventRepository->create($data);

                $this->requestRepository->createEventRequest(['event_id' => $event->id]);
                $event->update(['slug' => 'event'.'-'.Str::slug($data['title']).'-'.$event->id]);
                $event->translations()->create([
                    'locale' => 'ar',
                    'title' => $data['title'],
                    'description' => $data['description'],
                ]);

                $this->syncEventTags($event->id, $request);

                $uploadedFiles = $this->uploadedMediaFiles($request);

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
                            // ✅ بس نخزن temp ونـ dispatch — مفيش processing هنا
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
                                $index,
                                $photoValidationResults[$index] ?? null
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

                return $event;
            });

            $this->dispatchPostCommitJobs($event->id, $imageJobs, $videoJobs);
            TranslateEventJob::dispatch($event->id, $data['title'], $data['description']);

            $this->clearEventsCache($event->slug);

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

    private function validateUserPhotoPayload(Request $request): array
    {
        $request->validate([
            'photography_type' => ['required', 'in:normal,professional'],
            'urls' => ['required_without:photos', 'array', 'min:1'],
            'urls.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20460'],
            'photos' => ['nullable', 'array', 'min:1'],
            'photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20460'],
            'photo_descriptions' => ['required', 'array', 'min:1'],
            'photo_descriptions.*' => ['required', 'string', 'max:2000'],
            'photo_tags_json' => ['required', 'array', 'min:1'],
            'photo_tags_json.*' => ['required', 'json'],
            'photo_widths' => ['nullable', 'array'],
            'photo_heights' => ['nullable', 'array'],
            'photo_quality_scores' => ['nullable', 'array'],
            'photo_sharpness_scores' => ['nullable', 'array'],
            'photo_blur_scores' => ['nullable', 'array'],
            'photo_validation_statuses' => ['nullable', 'array'],
            'photo_validation_messages' => ['nullable', 'array'],
        ]);

        $uploadedFiles = $this->uploadedMediaFiles($request);
        $photoCount = count($uploadedFiles);

        if ($photoCount < 1) {
            throw ValidationException::withMessages([
                'urls' => ['At least one photo is required.'],
            ]);
        }

        $errors = [];
        $descriptions = $request->input('photo_descriptions', []);
        $photoTags = $request->input('photo_tags_json', []);

        if (count($descriptions) !== $photoCount) {
            $errors['photo_descriptions'] = ['Every photo must have a description.'];
        }

        if (count($photoTags) !== $photoCount) {
            $errors['photo_tags_json'] = ['Every photo must have at least one tag.'];
        }

        foreach ($uploadedFiles as $index => $file) {
            if (! $file instanceof UploadedFile) {
                $errors["urls.$index"] = ['Uploaded photo is invalid.'];

                continue;
            }

            $description = trim((string) ($descriptions[$index] ?? ''));

            if ($description === '') {
                $errors["photo_descriptions.$index"] = ['Photo description is required.'];
            }

            $tags = $this->decodePhotoTags($photoTags[$index] ?? null);

            if (empty($tags)) {
                $errors["photo_tags_json.$index"] = ['Every photo must have at least one tag.'];
            }

            if (count($tags) > 10) {
                $errors["photo_tags_json.$index"] = ['Each photo can have up to 10 tags.'];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $validationResults = [];
        $photographyType = (string) $request->input('photography_type');

        foreach ($uploadedFiles as $index => $file) {
            $result = $this->photoQualityService->validate($file, $photographyType);
            $validationResults[$index] = $result;

            if (! $result['accepted']) {
                $validationErrors = $result['errors'] ?: [$result['message']];

                if ($photographyType === 'professional') {
                    $errors["urls.$index"] = $validationErrors;
                } else {
                    $errors["urls.$index"] = ['Photo is invalid or unreadable.'];
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $validationResults;
    }

    private function stripUploadOnlyData(array &$data): void
    {
        foreach ([
            'urls',
            'photos',
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

    private function uploadedMediaFiles(Request $request): array
    {
        $files = $request->file('urls');

        if (empty($files)) {
            $files = $request->file('photos');
        }

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values($files);
    }

    private function photoMetadataForIndex(Request $request, int|string $index, ?array $validationResult = null): array
    {
        $metrics = $validationResult['metrics'] ?? [];
        $tags = $this->normalizePhotoTagsPayload($request->input("photo_tags_json.$index"));
        $photoTagIds = $this->resolvePhotoTagIds($tags);
        $validationErrors = $validationResult['errors'] ?? [];
        $validationMessage = $validationResult['message']
            ?? $request->input("photo_validation_messages.$index")
            ?? null;

        if (! empty($validationErrors)) {
            $validationMessage = implode('; ', $validationErrors);
        }

        return [
            'description' => trim((string) $request->input("photo_descriptions.$index")),
            'tags_json' => json_encode([
                'tags_id' => $photoTagIds,
                'new_tags' => $tags['new_tags'],
            ]),
            'tag_ids' => $photoTagIds,
            'quality_score' => $metrics['quality_score']
                ?? $request->input("photo_quality_scores.$index"),
            'sharpness_score' => $metrics['sharpness_score']
                ?? $request->input("photo_sharpness_scores.$index"),
            'blur_score' => $metrics['blur_score']
                ?? $request->input("photo_blur_scores.$index"),
            'megapixels' => $metrics['megapixels'] ?? null,
            'file_size_mb' => $metrics['file_size_mb']
                ?? $request->input("media_file_sizes_mb.$index"),
            'validation_status' => $validationResult['status']
                ?? $request->input("photo_validation_statuses.$index"),
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
    private function dispatchPostCommitJobs(int $eventId, array $imageJobs, array $videoJobs): void
    {
        try {
            if ($imageJobs !== []) {
                Bus::batch($imageJobs)
                    ->name("event-images:{$eventId}")
                    ->allowFailures()
                    ->finally(function (Batch $batch) use ($eventId): void {
                        GenerateEventAiTagsJob::dispatch($eventId);
                    })
                    ->dispatch();
            } else {
                GenerateEventAiTagsJob::dispatch($eventId);
            }
        } catch (\Throwable $exception) {
            Log::error('Event image batch dispatch failed', [
                'event_id' => $eventId,
                'message' => $exception->getMessage(),
            ]);
        }

        foreach ($videoJobs as $videoJob) {
            try {
                dispatch($videoJob);
            } catch (\Throwable $exception) {
                Log::error('Event video job dispatch failed', [
                    'event_id' => $eventId,
                    'message' => $exception->getMessage(),
                ]);
            }
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
