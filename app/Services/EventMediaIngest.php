<?php

namespace App\Services;

use App\Jobs\GenerateEventAiTagsJob;
use App\Jobs\ProcessEventVideoJob;
use App\Models\Events;
use App\Models\EventsImges;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                $name = (string) Str::uuid();
                $full = 'events/full/'.$name.'.'.($file->guessExtension() ?: 'jpg');
                $preview = 'events/preview/'.$name.'.jpg';
                Storage::disk('media')->put($full, fopen($file->getRealPath(), 'rb'));
                Storage::disk('media')->put($preview, (string) ($analysisPrice ? $analysis['preview_encoded'] : $analysis['image']->toJpeg(85)));
                $created[] = EventsImges::create([
                    'event_id' => $event->id, 'type' => 'image', 'full_url' => $full, 'preview_url' => $preview,
                    'original_disk' => 'media', 'preview_disk' => 'media', 'is_active' => 1,
                    'width' => $analysis['width'], 'height' => $analysis['height'],
                    'price' => $analysisPrice ? $analysis['price'] : 0, 'licence_type' => $analysis['plan'],
                ]);
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
