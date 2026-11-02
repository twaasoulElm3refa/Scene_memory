<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessEventImageJob;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use App\Services\EventTagCacheService;
use App\Services\ImageAnalysisService;
use App\Services\TagResolverService;
use App\Services\UnifiedMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessEventImageCloudinaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Bus::fake();
        config()->set('cloudinary_media.url', 'cloudinary://key:secret@example-cloud');
        config()->set('cloudinary_media.images_enabled', true);
        app()->instance(UnifiedMediaStorage::class, new FailingPreviewStorage);
    }

    public function test_cloudinary_failure_retains_paid_original_and_local_preview_when_fallback_is_enabled(): void
    {
        config()->set('cloudinary_media.allow_local_fallback', true);
        [$event, $job, $temp] = $this->job(10);

        $job->handle(app(ImageAnalysisService::class), app(TagResolverService::class), app(EventTagCacheService::class));

        $media = EventsImges::where('event_id', $event->id)->sole();
        $this->assertSame('media', $media->original_disk);
        $this->assertSame('media', $media->preview_disk);
        $this->assertSame(10.0, (float) $media->price);
        Storage::disk('media')->assertExists($media->full_url);
        Storage::disk('media')->assertExists($media->preview_url);
        Storage::disk('media')->assertMissing($temp);
    }

    public function test_cloudinary_failure_rethrows_for_retry_when_fallback_is_disabled(): void
    {
        config()->set('cloudinary_media.allow_local_fallback', false);
        [$event, $job, $temp] = $this->job(0);

        try {
            $job->handle(app(ImageAnalysisService::class), app(TagResolverService::class), app(EventTagCacheService::class));
            $this->fail('Expected the upload failure to trigger a queue retry.');
        } catch (RuntimeException $e) {
            $this->assertSame('Synthetic upload failure.', $e->getMessage());
        }

        $this->assertSame(0, EventsImges::where('event_id', $event->id)->count());
        Storage::disk('media')->assertExists($temp);
    }

    private function job(float $price): array
    {
        $owner = User::factory()->create(['is_active' => true]);
        $event = Events::create(['user_id' => $owner->id, 'title' => 'Photo', 'slug' => 'photo-'.$price, 'is_active' => true]);
        $temp = 'images_temp/input.png';
        Storage::disk('media')->put($temp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        return [$event, new ProcessEventImageJob($event->id, $temp, $price, [], 'media'), $temp];
    }
}

class FailingPreviewStorage extends UnifiedMediaStorage
{
    public function upload(\Illuminate\Http\UploadedFile|string $source, string $type, string $folder = 'public'): string
    {
        throw new RuntimeException('Synthetic upload failure.');
    }
}
