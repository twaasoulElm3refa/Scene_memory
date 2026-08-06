<?php

namespace App\Jobs;

use App\Models\EventsImges;
use App\Services\ImageAnalysisService;
use App\Services\TagResolverService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class ProcessEventImageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $eventId;
    public string $tempPath;
    public float $manualPrice = 0;
    public array $metadata = [];
    public function __construct(
        int $eventId,
        string $tempPath,
        float|int|string|null $manualPrice = 0,
        array $metadata = []
    ) {
        $this->eventId = $eventId;
        $this->tempPath = $tempPath;
        $this->manualPrice = is_numeric($manualPrice) && (float) $manualPrice > 0
            ? (float) $manualPrice
            : 0;
        $this->metadata = $metadata;
    }
    public function handle(
        ImageAnalysisService $imageAnalysisService,
        TagResolverService $tagResolver
    ): void {
        \Log::info('ProcessEventImageJob started', [
            'event_id' => $this->eventId,
            'temp_path' => $this->tempPath,
            'manual_price' => $this->manualPrice,
        ]);

        if (trim($this->tempPath) === '') {
            \Log::error('ProcessEventImageJob: empty temp path', [
                'event_id' => $this->eventId,
                'temp_path' => $this->tempPath,
            ]);

            return;
        }

        if (! Storage::disk('public')->exists($this->tempPath)) {
            \Log::error('ProcessEventImageJob: temp file not found', [
                'event_id' => $this->eventId,
                'temp_path' => $this->tempPath,
            ]);

            return;
        }

        $absolutePath = Storage::disk('public')->path($this->tempPath);

        if (! is_file($absolutePath)) {
            \Log::error('ProcessEventImageJob: temp path is not a file', [
                'event_id' => $this->eventId,
                'temp_path' => $this->tempPath,
                'absolute_path' => $absolutePath,
            ]);

            return;
        }

        try {
            $file = new File($absolutePath);

            $uploadedFile = new UploadedFile(
                $file->getPathname(),
                $file->getFilename(),
                $file->getMimeType(),
                null,
                true
            );

            $manager = $this->makeImageManager();
            $analysis = $imageAnalysisService->process($uploadedFile, $manager);

            $image = $analysis['image'];
            $preview = $analysis['preview_encoded'];
            $width = $analysis['width'];
            $height = $analysis['height'];
            $plan = $analysis['plan'];
            $price = $this->manualPrice;

            $filename = uniqid('', true).'.jpg';
            $fullPath = 'events/full/'.$filename;
            $previewPath = 'events/preview/'.$filename;
            Storage::disk('public')->put($fullPath, (string) $image->toJpeg(90));
            Storage::disk('public')->put($previewPath, (string) $preview);
            $eventImage = EventsImges::create([
                'event_id' => $this->eventId,
                'type' => 'image',
                'preview_url' => $previewPath,
                'full_url' => $fullPath,
                'width' => $width,
                'height' => $height,
                'size' => $width * $height,
                'price' => $price,
                'licence_type' => $plan,
                'is_active' => 1,
                'description' => $this->metadata['description'] ?? null,
                'tags_json' => $this->metadata['tags_json'] ?? null,
                'quality_score' => $this->metadata['quality_score'] ?? null,
                'sharpness_score' => $this->metadata['sharpness_score'] ?? null,
                'blur_score' => $this->metadata['blur_score'] ?? null,
                'megapixels' => $this->metadata['megapixels'] ?? null,
                'file_size_mb' => $this->metadata['file_size_mb'] ?? null,
                'validation_status' => $this->metadata['validation_status'] ?? null,
                'validation_message' => $this->metadata['validation_message'] ?? null,
            ]);

            // dispatch the image translation job
            TranslateImageJob::dispatch($eventImage->id, $this->metadata['description'] ?? 'description');
            $photoTagsJson = $this->metadata['tags_json'] ?? null;

            $decodedTags = [];

            if (is_string($photoTagsJson) && $photoTagsJson !== '') {
                $decodedTags = json_decode($photoTagsJson, true) ?: [];
            } elseif (is_array($photoTagsJson)) {
                $decodedTags = $photoTagsJson;
            }

            $existingTagIds = collect($decodedTags['tags_id'] ?? [])
                ->filter(fn ($id) => $id !== null && $id !== '' && is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $newTagIds = $tagResolver->resolveIds(
                $decodedTags['new_tags'] ?? [],
                'user'
            );

            $tagIds = collect([...$existingTagIds, ...$newTagIds])
                ->unique()
                ->values()
                ->all();

            if (! empty($tagIds)) {
                $eventImage->tags()->syncWithoutDetaching($tagIds);
            }

        } catch (\Throwable $e) {
            \Log::error('ProcessEventImageJob: failed', [
                'event_id' => $this->eventId,
                'temp_path' => $this->tempPath,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            Storage::disk('public')->delete($this->tempPath);
        }
    }

    public function failed(\Throwable $exception): void
    {
        if (trim($this->tempPath) !== '') {
            Storage::disk('public')->delete($this->tempPath);
        }

        \Log::error('ProcessEventImageJob: permanently failed', [
            'event_id' => $this->eventId,
            'temp_path' => $this->tempPath,
            'message' => $exception->getMessage(),
        ]);
    }

    private function makeImageManager(): ImageManager
    {
        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver);
        }

        return new ImageManager(new GdDriver);
    }
}
