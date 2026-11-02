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
        $fingerprint = hash('sha256', $this->eventId.'|'.$this->sourceDisk.'|'.$this->tempPath);
        $existing = EventsImges::where('event_id', $this->eventId)
            ->where(fn ($query) => $query->where('source_fingerprint', $fingerprint)
                ->orWhere('full_url', 'like', 'events/full/'.$fingerprint.'.%'))
            ->first();
        if ($existing && $existing->media_status === 'ready' && $existing->preview_url
            && ($existing->preview_disk === 'cloudinary' || Storage::disk('media')->exists($existing->preview_url))
            && ($existing->original_disk === 'cloudinary'
                ? $existing->cloudinaryOriginalAsset()->where('status', 'ready')->exists()
                : Storage::disk('media')->exists($existing->full_url))) {
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

            $filename = $fingerprint;
            $extension = $uploadedFile->guessExtension() ?: 'jpg';
            $fullPath = 'events/full/'.$filename.'.'.$extension;
            $previewPath = 'events/preview/'.$filename.'.jpg';
            $cloud = app(UnifiedMediaStorage::class);
            if (! Storage::disk('media')->exists($fullPath)) {
                Storage::disk('media')->put($fullPath, fopen($absolutePath, 'rb'));
                @chmod(Storage::disk('media')->path($fullPath), 0600);
            }
            if (! Storage::disk('media')->exists($previewPath)) Storage::disk('media')->put($previewPath, (string) $preview);
            \Log::info('media_preview_generated', ['event_id' => $this->eventId, 'image_identifier' => $imageIdentifier, 'disk' => 'media']);
            $eventImage = EventsImges::updateOrCreate(['event_id' => $this->eventId, 'source_fingerprint' => $filename], [
                'original_disk' => 'media', 'preview_disk' => 'media',
                'event_id' => $this->eventId,
                'type' => 'image',
                'preview_url' => $previewPath,
                'fallback_preview_url' => null,
                'full_url' => $fullPath,
                'local_original_path' => $fullPath,
                'width' => $width,
                'height' => $height,
                'media_bytes' => filesize($absolutePath) ?: null,
                'media_format' => $extension,
                'media_status' => $cloud->enabled('image') ? 'cloudinary_pending' : 'ready',
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
            if ($cloud->enabled('image')) {
                try {
                    $original = $cloud->authenticatedOriginal(Storage::disk('media')->path($fullPath), $this->eventId, $fingerprint, (int) $eventImage->id);
                    $eventImage->update(['full_url' => $original, 'original_disk' => 'cloudinary', 'media_status' => 'processing']);
                    app(\App\Services\IndependentMediaBackup::class)->backUp($eventImage, $eventImage->cloudinaryOriginalAsset()->firstOrFail());
                } catch (\Throwable $e) {
                    $eventImage->update(['media_status' => 'cloudinary_failed']);
                    throw $e;
                }
            }
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
            $eventImage->update(['media_status' => 'ready']);
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
