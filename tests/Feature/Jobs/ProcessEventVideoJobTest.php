<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessEventVideoJob;
use App\Models\Events;
use App\Models\EventsImges;
use App\Services\EventTagCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessEventVideoJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_watermark_failure_does_not_create_media_and_retry_uses_moved_source(): void
    {
        Storage::fake('public');

        $event = Events::create([
            'title' => 'Video watermark event',
            'description' => 'Video watermark processing test.',
            'slug' => 'video-watermark-event',
        ]);

        $tempPath = 'videos_temp/upload.mp4';
        Storage::disk('public')->put($tempPath, 'original-video');

        $job = new class($event->id, $tempPath) extends ProcessEventVideoJob
        {
            public int $watermarkAttempts = 0;

            protected function makeWatermarkedPreviewVideo(string $videoPath, string $debugId): ?string
            {
                $this->watermarkAttempts++;

                if ($this->watermarkAttempts === 1) {
                    return null;
                }

                $previewPath = 'videos/preview_wm_upload.mp4';
                Storage::disk('public')->put($previewPath, 'watermarked-video');

                return $previewPath;
            }
        };

        try {
            $job->handle(app(EventTagCacheService::class));
            $this->fail('The job should fail when no watermarked preview is produced.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Watermarked preview video generation failed.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('events_images', [
            'event_id' => $event->id,
            'type' => 'video',
        ]);
        Storage::disk('public')->assertMissing($tempPath);
        Storage::disk('public')->assertExists('videos/upload.mp4');

        $job->handle(app(EventTagCacheService::class));

        $this->assertDatabaseHas('events_images', [
            'event_id' => $event->id,
            'type' => 'video',
            'preview_url' => 'videos/preview_wm_upload.mp4',
            'full_url' => 'videos/upload.mp4',
        ]);

        $job->handle(app(EventTagCacheService::class));

        $this->assertSame(1, EventsImges::query()->where('event_id', $event->id)->count());
        $this->assertSame(2, $job->watermarkAttempts);
    }
}
