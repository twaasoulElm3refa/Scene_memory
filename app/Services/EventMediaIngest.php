<?php

namespace App\Services;

use App\Jobs\GenerateEventAiTagsJob;
use App\Jobs\ProcessEventVideoJob;
use App\Models\Events;
use App\Models\EventsImges;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class EventMediaIngest
{
    public function upload(Events $event, array $files, bool $analysisPrice = false): array
    {
        $created = [];
        foreach ($files as $file) {
            if (str_starts_with((string) $file->getMimeType(), 'image/')) {
                $analysis = app(ImageAnalysisService::class)->process($file, new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver, decodeAnimation: false, strip: true));
                $fingerprint = hash('sha256', $event->id.'|'.hash_file('sha256', $file->getRealPath()));
                $prior = EventsImges::where('event_id', $event->id)->where('source_fingerprint', $fingerprint)->first();
                if ($prior && $prior->media_status === 'ready' && Storage::disk('media')->exists($prior->preview_url)) {
                    $created[] = $prior;
                    continue;
                }
                $name = $fingerprint;
                $full = 'events/full/'.$name.'.'.($file->guessExtension() ?: 'jpg');
                $preview = 'events/preview/'.$name.'.jpg';
                if (! Storage::disk('media')->exists($full)) {
                    Storage::disk('media')->put($full, fopen($file->getRealPath(), 'rb'));
                    @chmod(Storage::disk('media')->path($full), 0600);
                }
                Storage::disk('media')->put($preview, (string) $analysis['preview_encoded']);
                $cloud = app(UnifiedMediaStorage::class);
                $mediaRecord = EventsImges::updateOrCreate(['event_id' => $event->id, 'source_fingerprint' => $fingerprint], [
                        'event_id' => $event->id, 'type' => 'image', 'full_url' => $full, 'preview_url' => $preview,
                        'fallback_preview_url' => null, 'local_original_path' => $full,
                        'original_disk' => 'media', 'preview_disk' => 'media', 'is_active' => 1,
                        'width' => $analysis['width'], 'height' => $analysis['height'],
                        'media_bytes' => $file->getSize(), 'media_format' => $file->guessExtension(),
                        'media_status' => $cloud->enabled('image') ? 'cloudinary_pending' : 'ready',
                        'price' => $analysisPrice ? $analysis['price'] : 0, 'licence_type' => $analysis['plan'],
                    ]);
                if ($cloud->enabled('image')) {
                    try {
                        $original = $cloud->authenticatedOriginal(Storage::disk('media')->path($full), (int) $event->id, $fingerprint, (int) $mediaRecord->id);
                        $mediaRecord->update(['full_url' => $original, 'original_disk' => 'cloudinary', 'media_status' => 'ready']);
                        app(IndependentMediaBackup::class)->backUp($mediaRecord, $mediaRecord->cloudinaryOriginalAsset()->firstOrFail());
                    } catch (\Throwable $e) {
                        $mediaRecord->update(['media_status' => 'cloudinary_failed']);
                        throw $e;
                    }
                }
                app(MediaTierService::class)->assignTierToMedia($mediaRecord);
                $created[] = $mediaRecord;
            } else {
                $path = $file->store('videos_temp', 'media');
                ProcessEventVideoJob::dispatch($event->id, $path, true, 'media', $analysisPrice ? null : 0);
            }
        }
        if ($created !== []) {
            GenerateEventAiTagsJob::dispatch((int) $event->id);
        }
        app(EventTagCacheService::class)->invalidateEvent((int) $event->id);

        return $created;
    }
}
