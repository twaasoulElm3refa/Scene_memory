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
use Cloudinary\Api\Exception\NotFound as CloudinaryNotFound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessEventImageCloudinaryTest extends TestCase
{
    use RefreshDatabase;

    private FakeAuthenticatedOriginalStorage $cloud;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Bus::fake();
        config()->set('cloudinary_media.url', 'cloudinary://key:secret@example-cloud');
        config()->set('cloudinary_media.images_enabled', true);
        config()->set('cloudinary_media.backup_disk', null);
        $this->cloud = new FakeAuthenticatedOriginalStorage;
        app()->instance(UnifiedMediaStorage::class, $this->cloud);
    }

    public function test_pending_event_stores_only_clean_authenticated_original_in_cloudinary(): void
    {
        [$event, $job, $temp] = $this->job(10, false);
        $this->runJob($job);
        $media = EventsImges::where('event_id', $event->id)->sole();
        $asset = $media->cloudinaryOriginalAsset()->sole();
        $this->assertSame('cloudinary', $media->original_disk);
        $this->assertSame('media', $media->preview_disk);
        $this->assertSame('ready', $media->media_status);
        $this->assertSame('authenticated', $asset->delivery_type);
        $this->assertSame('pending', $asset->backup_status);
        $this->assertSame(10.0, (float) $media->price);
        $this->assertSame(1, $this->cloud->uploads);
        Storage::disk('media')->assertExists($media->local_original_path);
        Storage::disk('media')->assertExists($media->preview_url);
        Storage::disk('media')->assertMissing($temp);
        $this->assertNotSame(Storage::disk('media')->get($media->local_original_path), Storage::disk('media')->get($media->preview_url));
        $this->assertNull($this->cloud->url($media->getRawOriginal('full_url')));
        $this->get(route('media.preview', $media))->assertNotFound();
        $this->runJob($job);
        $this->assertSame(1, $this->cloud->uploads);
        $this->assertSame(1, EventsImges::where('event_id', $event->id)->count());
    }

    public function test_cloudinary_failure_retains_private_original_and_preview_for_retry(): void
    {
        $this->cloud->failUpload = true;
        [$event, $job, $temp] = $this->job(0, true);
        try {
            $this->runJob($job);
            $this->fail('Expected Cloudinary upload failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Synthetic upload failure.', $e->getMessage());
        }
        $media = EventsImges::where('event_id', $event->id)->sole();
        $this->assertSame('cloudinary_failed', $media->media_status);
        $this->assertSame('media', $media->original_disk);
        Storage::disk('media')->assertExists($media->local_original_path);
        Storage::disk('media')->assertExists($media->preview_url);
        Storage::disk('media')->assertExists($temp);
        $this->cloud->failUpload = false;
        $this->runJob($job);
        $this->assertSame('ready', $media->fresh()->media_status);
        $this->assertSame(1, $this->cloud->uploads);
        $this->assertSame(1, EventsImges::where('event_id', $event->id)->count());
    }

    public function test_disabled_image_flag_keeps_new_image_private_and_preview_local(): void
    {
        config()->set('cloudinary_media.images_enabled', false);
        [$event, $job] = $this->job(5, true);
        $this->runJob($job);
        $media = EventsImges::where('event_id', $event->id)->sole();
        $this->assertSame('media', $media->original_disk);
        $this->assertSame('media', $media->preview_disk);
        $this->assertSame(0, $this->cloud->uploads);
    }

    private function runJob(ProcessEventImageJob $job): void
    {
        $job->handle(app(ImageAnalysisService::class), app(TagResolverService::class), app(EventTagCacheService::class));
    }

    private function job(float $price, bool $active): array
    {
        $owner = User::factory()->create(['is_active' => true]);
        $event = Events::create(['user_id' => $owner->id, 'title' => 'Photo', 'slug' => 'photo-'.$price.'-'.$active, 'is_active' => $active]);
        $temp = 'images_temp/input.png';
        Storage::disk('media')->put($temp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        return [$event, new ProcessEventImageJob($event->id, $temp, $price, [], 'media'), $temp];
    }
}

class FakeAuthenticatedOriginalStorage extends UnifiedMediaStorage
{
    public int $uploads = 0;
    public bool $failUpload = false;

    protected function fetchUsage(): array
    {
        return ['credits' => ['usage' => 0, 'limit' => 25]];
    }

    protected function fetchAuthenticatedAsset(string $publicId): array
    {
        throw new CloudinaryNotFound('Synthetic not found.');
    }

    protected function sendAuthenticatedUpload(string $file, string $publicId): array
    {
        if ($this->failUpload) throw new RuntimeException('Synthetic upload failure.');
        $this->uploads++;
        return ['public_id' => $publicId, 'asset_id' => 'asset-1', 'resource_type' => 'image',
            'type' => 'authenticated', 'format' => 'png', 'version' => 123, 'bytes' => filesize($file),
            'width' => 1, 'height' => 1];
    }
}
