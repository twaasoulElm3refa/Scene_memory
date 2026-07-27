<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\EventsImges;
use App\Services\EventAiTagsPersistenceService;
use App\Services\GenerateImageTagsService;
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
        EventAiTagsPersistenceService $persistenceService
    ): void {
        $event = Events::query()->find($this->eventId);

        if ($event === null) {
            Log::warning('event_ai_tags_event_not_found', [
                'event_id' => $this->eventId,
            ]);

            return;
        }

        $images = $event->images()
            ->orderBy('id')
            ->get()
            ->filter(fn (EventsImges $image) => $this->isStoredImage($image))
            ->take(max(0, (int) config('ai_tags.images_limit', 5)))
            ->values();

        $imagesByIndex = [];
        $storedPaths = [];

        foreach ($images as $offset => $image) {
            $imageIndex = $offset + 1;
            $imagesByIndex[$imageIndex] = $image;
            $storedPaths[] = (string) $image->full_url;
        }

        if ($storedPaths === []) {
            Log::warning('event_ai_tags_no_stored_images', [
                'event_id' => $this->eventId,
            ]);
        }

        Log::info('GenerateEventAiTagsJob: AI request started', [
            'event_id' => $this->eventId,
            'images_count' => count($storedPaths),
            'model' => config('services.openrouter.model'),
        ]);

        $result = $tagsService->handleStoredImages(
            title: (string) $event->title,
            description: $event->description,
            storedPaths: $storedPaths,
            language: (string) config('ai_tags.language', 'ar'),
            eventId: $this->eventId
        );

        Log::info('GenerateEventAiTagsJob: AI request completed', [
            'event_id' => $this->eventId,
            'event_tags_count' => count($result['event_tags'] ?? []),
            'images_results_count' => count($result['images'] ?? []),
        ]);

        try {
            $persistenceService->persist($event, $result, $imagesByIndex);
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
}
