<?php

namespace App\Jobs;

use App\Models\EventsImges;
use App\Services\EventTagCacheService;
use App\Services\ImageAnalysisService;
use App\Services\TagResolverService;
use App\Services\UnifiedMediaStorage;
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

    public string $sourceDisk = 'public';

    public function __construct(
        int $eventId,
        string $tempPath,
        float|int|string|null $manualPrice = 0,
        array $metadata = [],
        string $sourceDisk = 'public'
    ) {
        $this->eventId = $eventId;
        $this->tempPath = $tempPath;
        $this->manualPrice = is_numeric($manualPrice) && (float) $manualPrice > 0
            ? (float) $manualPrice
            : 0;
        $this->metadata = $metadata;
        $this->sourceDisk = $sourceDisk;
    }

    public function handle(
        ImageAnalysisService $imageAnalysisService,
        TagResolverService $tagResolver,
        EventTagCacheService $cache
    ): void {
        $imageIdentifier = substr(hash('sha256', $this->eventId.'|'.$this->sourceDisk.'|'.$this->tempPath), 0, 16);
        $existing = EventsImges::where('event_id', $this->eventId)
            ->where('full_url', 'like', 'events/full/'.hash('sha256', $this->eventId.'|'.$this->sourceDisk.'|'.$this->tempPath).'.%')
            ->first();
        if ($existing && $existing->preview_url && Storage::disk('media')->exists($existing->full_url)
            && ($existing->preview_disk === 'cloudinary' || Storage::disk('media')->exists($existing->preview_url))) {
            Storage::disk($this->sourceDisk)->delete($this->tempPath);
            \Log::info('image_processing_completed', ['event_id' => $this->eventId, 'media_id' => $existing->id, 'image_identifier' => $imageIdentifier, 'preview_disk' => $existing->preview_disk, 'duplicate' => true]);
            return;
        }
        \Log::info('image_processing_started', ['event_id' => $this->eventId, 'image_identifier' => $imageIdentifier, 'source_disk' => $this->sourceDisk]);

        if (trim($this->tempPath) === '') {
            \Log::error('image_processing_failed', ['event_id' => $this->eventId, 'image_identifier' => $imageIdentifier, 'reason' => 'empty_source_path']);
            throw new \RuntimeException('Image source path is empty.');
        }

        if (! Storage::disk($this->sourceDisk)->exists($this->tempPath)) {
            \Log::error('image_processing_failed', ['event_id' => $this->eventId, 'image_identifier' => $imageIdentifier, 'reason' => 'source_missing']);
            throw new \RuntimeException('Image source is unavailable.');
        }

        $absolutePath = Storage::disk($this->sourceDisk)->path($this->tempPath);

        if (! is_file($absolutePath)) {
            \Log::error('image_processing_failed', ['event_id' => $this->eventId, 'image_identifier' => $imageIdentifier, 'reason' => 'source_not_file']);
            throw new \RuntimeException('Image source is not a file.');
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

            $filename = hash('sha256', $this->eventId.'|'.$this->sourceDisk.'|'.$this->tempPath);
            $extension = $uploadedFile->guessExtension() ?: 'jpg';
            $fullPath = 'events/full/'.$filename.'.'.$extension;
            $previewPath = 'events/preview/'.$filename.'.jpg';
            $cloud = app(UnifiedMediaStorage::class);
            $existing = EventsImges::where('event_id', $this->eventId)->where('full_url', $fullPath)->first();
            $localPreviewPath = $previewPath;
            if ($existing && $existing->preview_url && ($cloud->isCloudinary($existing->preview_url) || Storage::disk('media')->exists($existing->preview_url))) {
                $previewPath = $existing->preview_url;
                $localPreviewPath = $existing->fallback_preview_url ?: $localPreviewPath;
            } else {
                Storage::disk('media')->put($fullPath, fopen($absolutePath, 'rb'));
                Storage::disk('media')->put($previewPath, (string) $preview);
                if ($cloud->enabled('image') && \App\Models\Events::whereKey($this->eventId)->where('is_active', true)->exists()) {
                    try {
                        $previewPath = $cloud->upload(Storage::disk('media')->path($previewPath), 'image', 'events-preview');
                    } catch (\Throwable $e) {
                        $fallback = (bool) config('cloudinary_media.allow_local_fallback');
                        \Log::warning($fallback ? 'media_local_fallback_used' : 'cloudinary_preview_upload_failed', ['event_id' => $this->eventId, 'image_identifier' => $imageIdentifier, 'resource_type' => 'image', 'disk' => 'media', 'exception_category' => get_class($e)]);
                        if (! $fallback) throw $e;
                    }
                }
            }
            $eventImage = EventsImges::updateOrCreate(['event_id' => $this->eventId, 'full_url' => $fullPath], [
                'original_disk' => 'media', 'preview_disk' => $cloud->isCloudinary($previewPath) ? 'cloudinary' : 'media',
                'event_id' => $this->eventId,
                'type' => 'image',
                'preview_url' => $previewPath,
                'fallback_preview_url' => $cloud->isCloudinary($previewPath) ? $localPreviewPath : null,
                'full_url' => $fullPath,
                'width' => $width,
                'height' => $height,
                'media_bytes' => filesize($absolutePath) ?: null,
                'media_format' => $extension,
                'media_status' => 'ready',
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
            app(\App\Services\MediaTierService::class)->assignTierToMedia($eventImage);

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

            $cache->invalidateEvent($this->eventId, [(int) $eventImage->id]);
            Storage::disk($this->sourceDisk)->delete($this->tempPath);
            GenerateMediaThumbnailJob::dispatch((int) $eventImage->id);
            \Log::info('image_processing_completed', ['event_id' => $this->eventId, 'media_id' => $eventImage->id, 'image_identifier' => $imageIdentifier, 'preview_disk' => $eventImage->preview_disk, 'duplicate' => false]);

        } catch (\Throwable $e) {
            if (isset($previewPath)) app(UnifiedMediaStorage::class)->deleteUnreferencedEventPreview($previewPath);
            \Log::error('image_processing_failed', ['event_id' => $this->eventId, 'image_identifier' => $imageIdentifier, 'exception_category' => get_class($e)]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        if (trim($this->tempPath) !== '') {
            Storage::disk($this->sourceDisk)->delete($this->tempPath);
        }

        \Log::error('image_processing_permanently_failed', ['event_id' => $this->eventId, 'image_identifier' => substr(hash('sha256', $this->eventId.'|'.$this->sourceDisk.'|'.$this->tempPath), 0, 16), 'exception_category' => get_class($exception)]);
    }

    private function makeImageManager(): ImageManager
    {
        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver, decodeAnimation: false, strip: true);
        }

        return new ImageManager(new GdDriver, decodeAnimation: false, strip: true);
    }
}
