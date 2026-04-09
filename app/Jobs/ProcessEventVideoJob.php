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
use Intervention\Image\Typography\FontFactory;

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
        $fileContents = Storage::disk('public')->get($this->filePath);
        $finalPath = 'videos/' . basename($this->filePath);
        Storage::disk('public')->put($finalPath, $fileContents);

        // ✅ استخرج thumbnail وعمل preview
        $previewPath = $this->makeVideoPreview($this->filePath);

        eventsImges::create([
            'event_id'    => $this->eventId,
            'preview_url' => $previewPath ?? $this->filePath, // ✅
            'full_url'    => $finalPath,
            'price'       => 15,
            'is_active'   => 1,
        ]);

        $this->clearEventsCache($this->eventId);
        $this->clearEventCache($this->eventId);
        Storage::disk('public')->delete($this->filePath);
    }

    private function makeVideoPreview(string $videoPath): ?string
    {
        // ✅ الـ absolute path للفيديو
        $storagePath = Storage::disk('public')->path($videoPath);

        // ✅ تأكد إن الفيديو موجود فعلاً
        if (!file_exists($storagePath)) {
            \Log::error("ProcessEventVideoJob: Video not found at {$storagePath}");
            return null;
        }

        $thumbFilename = 'events/preview/thumb_' . uniqid() . '.jpg';
        $thumbFullPath = Storage::disk('public')->path($thumbFilename);

        // ✅ تأكد إن الـ directory موجود
        $thumbDir = dirname($thumbFullPath);
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0775, true);
        }

        // ✅ ffmpeg command محسّن مع error output
        $cmd = sprintf(
            'ffmpeg -y -i %s -ss 00:00:01 -vframes 1 -f image2 %s 2>&1',
            escapeshellarg($storagePath),
            escapeshellarg($thumbFullPath)
        );

        exec($cmd, $output, $returnCode);

        // ✅ تحقق من نجاح ffmpeg وإن الملف اتعمل وحجمه > 0
        if ($returnCode !== 0 || !file_exists($thumbFullPath) || filesize($thumbFullPath) === 0) {
            \Log::error("ProcessEventVideoJob: ffmpeg failed. Code: {$returnCode}. Output: " . implode("\n", $output));

            // cleanup لو اتعمل ملف فاضي
            if (file_exists($thumbFullPath)) {
                unlink($thumbFullPath);
            }
            return null;
        }

        try {
            $manager = new ImageManager(new Driver());

            // ✅ اقرأ من الـ absolute path مباشرة مش من storage
            $image = $manager->read($thumbFullPath);

            $image->blur(6);

            $image->text('© Protected', $image->width() / 2, $image->height() / 2, function (FontFactory $font) {
                $font->size(42);
                $font->color([255, 255, 255, 100]);
                $font->align('center');
                $font->valign('middle');
                $font->angle(30);
            });

            // ✅ حفظ الـ preview المعدّل
            Storage::disk('public')->put($thumbFilename, $image->toJpeg(75));

            return $thumbFilename;

        } catch (\Exception $e) {
            \Log::error("ProcessEventVideoJob: Image processing failed: " . $e->getMessage());

            // cleanup
            if (file_exists($thumbFullPath)) {
                unlink($thumbFullPath);
            }
            return null;
        }
    }

    private function clearEventsCache($slug = null)
    {
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        Cache::forget("events_single_{$slug}");
        foreach ($locales as $locale) {
            Cache::forget("events_single_{$slug}_".$locale);
        }
        Cache::flush();
    }

    public function clearEventCache(string $slug): void
    {
        Cache::tags(['events'])->forget(
            'event_' . strtolower(trim($slug)) . '_' . app()->getLocale()
        );
    }
}
