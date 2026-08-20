<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\EventsImges;
use App\Services\EventAiTagsPersistenceService;
use App\Services\GenerateImageTagsService;
use App\Services\VideoFrameExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateEventAiTagsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue((string) config('ai_tags.queue', 'default'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("event-ai-tags:{$this->eventId}"))
                ->releaseAfter(30)
                ->expireAfter((int) config('ai_tags.overlap_expire_after', 600)),
        ];
    }

    public function handle(
        GenerateImageTagsService $tagsService,
        EventAiTagsPersistenceService $persistenceService,
        VideoFrameExtractor $videoFrameExtractor
    ): void {
        $event = Events::query()->find($this->eventId);

        if ($event === null) {
            Log::warning('event_ai_tags_event_not_found', [
                'event_id' => $this->eventId,
            ]);

            return;
        }

        $visualInputLimit = max(0, (int) config('ai_tags.images_limit', 5));
        $analysisBatches = [];
        $temporaryFramePaths = [];
        $media = $event->images()->orderBy('id')->get();
        $imagePaths = [];
        $imageMediaByIndex = [];

        foreach ($media->filter(fn (EventsImges $item) => $this->isStoredImage($item))->take($visualInputLimit) as $image) {
            $imagePaths[] = (string) $image->full_url;
            $imageMediaByIndex[count($imagePaths)] = $image;
        }

        if ($imagePaths !== []) {
            $analysisBatches[] = [
                'paths' => $imagePaths,
                'media_by_index' => $imageMediaByIndex,
            ];
        }

        foreach ($media->filter(fn (EventsImges $item) => $this->isStoredVideo($item)) as $video) {
            $frames = $videoFrameExtractor->extract(
                $video,
                max(1, (int) config('ai_tags.video_frames_limit', 5))
            );
            $temporaryFramePaths = [...$temporaryFramePaths, ...$frames];
            $videoMediaByIndex = [];

            foreach ($frames as $index => $framePath) {
                $videoMediaByIndex[$index + 1] = $video;
            }

            if ($frames !== []) {
                $analysisBatches[] = [
                    'paths' => $frames,
                    'media_by_index' => $videoMediaByIndex,
                ];
            }
        }

        if ($analysisBatches === []) {
            Log::warning('event_ai_tags_no_visual_inputs', [
                'event_id' => $this->eventId,
            ]);
            $analysisBatches[] = [
                'paths' => [],
                'media_by_index' => [],
            ];
        }

        $eventRequestCreate = $event->requests()->first();

        if ($eventRequestCreate?->exists) {
            try {
                $flagged = $tagsService->flagEventContent(
                    title: (string) $event->title,
                    description: $event->description,
                    eventId: $this->eventId,
                    eventRequestCreateId: (int) $eventRequestCreate->id
                );

                $eventRequestCreate->ai_flagged = $flagged;
                $saved = $eventRequestCreate->save();
                $eventRequestCreate->refresh();

                Log::info('AI moderation flag saved', [
                    'event_id' => $event->id ?? null,
                    'event_request_create_id' => $eventRequestCreate->id,
                    'save_result' => $saved,
                    'parsed_flagged' => $flagged,
                    'stored_ai_flagged' => $eventRequestCreate->ai_flagged,
                    'stored_type' => get_debug_type(
                        $eventRequestCreate->ai_flagged
                    ),
                ]);
            } catch (Throwable $exception) {
                Log::error('AI moderation processing failed', [
                    'event_id' => $event->id ?? null,
                    'event_request_create_id' => $eventRequestCreate->id ?? null,
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);
            }
        } else {
            Log::info('AI moderation skipped for direct event creation', [
                'event_id' => $event->id,
            ]);
        }

        Log::info('GenerateEventAiTagsJob: AI request started', [
            'event_id' => $this->eventId,
            'images_count' => collect($analysisBatches)->sum(fn (array $batch) => count($batch['paths'])),
            'model' => config('services.openrouter.model'),
        ]);

        try {
            $eventTagsCount = 0;
            $mediaResultsCount = 0;

            foreach ($analysisBatches as $batch) {
                $result = $tagsService->handleStoredImages(
                    title: (string) $event->title,
                    description: $event->description,
                    storedPaths: $batch['paths'],
                    language: (string) config('ai_tags.language', 'ar'),
                    eventId: $this->eventId
                );
                $eventTagsCount += count($result['event_tags'] ?? []);
                $mediaResultsCount += count($result['images'] ?? []);

                try {
                    $persistenceService->persist($event, $result, $batch['media_by_index']);
                } catch (Throwable $exception) {
                    Log::error('GenerateEventAiTagsJob: tags persistence failed', [
                        'event_id' => $this->eventId,
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);

                    throw $exception;
                }
            }

            Log::info('GenerateEventAiTagsJob: AI request completed', [
                'event_id' => $this->eventId,
                'event_tags_count' => $eventTagsCount,
                'images_results_count' => $mediaResultsCount,
            ]);
        } finally {
            $videoFrameExtractor->cleanup($temporaryFramePaths);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateEventAiTagsJob: permanently failed', [
            'event_id' => $this->eventId,
            'job_id' => $this->job?->getJobId(),
            'attempts' => $this->attempts(),
            'message' => $exception->getMessage(),
        ]);
    }

    private function isStoredImage(EventsImges $image): bool
    {
        $path = ltrim((string) $image->full_url, '/');

        if (
            $path === ''
            || $image->type === 'video'
            || str_starts_with($path, 'videos/')
        ) {
            return false;
        }

        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'heic', 'heif', 'tif', 'tiff'];

        if (! in_array($extension, $imageExtensions, true)) {
            return false;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            Log::warning('event_ai_tags_image_missing', [
                'event_id' => $this->eventId,
                'events_imges_id' => $image->getKey(),
            ]);

            return false;
        }

        try {
            return str_starts_with((string) $disk->mimeType($path), 'image/');
        } catch (Throwable) {
            return true;
        }
    }

    private function isStoredVideo(EventsImges $media): bool
    {
        $path = ltrim((string) $media->full_url, '/');

        if ($path === '' || $media->type !== 'video') {
            return false;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            Log::warning('event_ai_tags_video_missing', [
                'event_id' => $this->eventId,
                'events_imges_id' => $media->getKey(),
            ]);

            return false;
        }

        return true;
    }
}
