<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProcessEventVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $eventId;
    public $filePath;

    public function __construct($eventId, $filePath)
    {
        $this->eventId = $eventId;
        $this->filePath = $filePath;
    }

    public function handle()
    {
        $event = Events::find($this->eventId);

        if (!$event) {
            \Log::warning('ProcessEventVideoJob: Event not found', ['event_id' => $this->eventId]);
            return;
        }

        $fileContents = Storage::disk('public')->get($this->filePath);
        $finalPath = 'videos/' . basename($this->filePath);
        Storage::disk('public')->put($finalPath, $fileContents);

        $previewPath = $this->makeVideoPreview($finalPath);

        \Log::info('ProcessEventVideoJob: preview result', [
            'event_id'     => $this->eventId,
            'preview_path' => $previewPath,
            'final_path'   => $finalPath,
        ]);

        eventsImges::create([
            'event_id'    => $this->eventId,
            'preview_url' => $previewPath,
            'full_url'    => $finalPath,
            'price'       => 15,
            'is_active'   => 1,
        ]);

        // حذف الملف المؤقت بعد ما اتنقل
        Storage::disk('public')->delete($this->filePath);

        $this->clearEventsCache($event->slug);
    }

    private function makeVideoPreview(string $videoPath): ?string
    {
        $storagePath = Storage::disk('public')->path($videoPath);

        if (!file_exists($storagePath)) {
            \Log::error('makeVideoPreview: Video file not found on disk', [
                'storage_path' => $storagePath,
            ]);
            return null;
        }

        $thumbFilename = 'events/preview/thumb_' . uniqid() . '.jpg';
        $thumbFullPath = Storage::disk('public')->path($thumbFilename);

        $thumbDir = dirname($thumbFullPath);
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0775, true);
        }

        $cmd = sprintf(
            'ffmpeg -y -i %s -ss 00:00:01 -vframes 1 -f image2 %s 2>&1',
            escapeshellarg($storagePath),
            escapeshellarg($thumbFullPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($thumbFullPath) || filesize($thumbFullPath) === 0) {
            \Log::error('makeVideoPreview: ffmpeg failed', [
                'cmd'         => $cmd,
                'return_code' => $returnCode,
                'output'      => implode("\n", $output),
            ]);

            if (file_exists($thumbFullPath)) {
                unlink($thumbFullPath);
            }

            return null;
        }

        try {
            $manager = new ImageManager(new Driver());
            $image   = $manager->read($thumbFullPath);

            $image->blur(12);

            $watermarkPath = public_path('images/watermark.png');
            if (file_exists($watermarkPath)) {
                $watermark = $manager->read($watermarkPath);
                $watermark->scale(
                    width:  (int) ($image->width() * 0.75),
                    height: (int) ($image->height() * 0.55)
                );
                $image->place($watermark, 'center', 0, 0, 40);
            } else {
                \Log::warning('makeVideoPreview: watermark.png not found', ['path' => $watermarkPath]);
            }

            $image->toJpeg(75)->save($thumbFullPath);

            return $thumbFilename;

        } catch (\Throwable $e) {
            \Log::error('makeVideoPreview: Image processing failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            if (file_exists($thumbFullPath)) {
                unlink($thumbFullPath);
            }

            return null;
        }
    }

    private function clearEventsCache(?string $slug = null): void
    {
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];

        if ($slug) {
            Cache::forget("events_single_{$slug}");
            foreach ($locales as $locale) {
                Cache::forget("events_single_{$slug}_{$locale}");
            }

            try {
                Cache::tags(['events'])->forget(
                    'event_' . strtolower(trim($slug)) . '_' . app()->getLocale()
                );
            } catch (\Throwable $e) {
                // بعض الـ cache drivers مش بتدعم tags
            }
        }

        Cache::flush();
    }
}
