<?php

namespace Tests\Feature\Media;

use App\Models\CloudinaryMediaAsset;
use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use App\Jobs\PromotePublishedMediaPreviews;
use App\Services\UnifiedMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class UnifiedMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Cache::forget('cloudinary:usage');
        config()->set('cloudinary_media.url', 'cloudinary://key:secret@example-cloud');
        config()->set('cloudinary_media.images_enabled', true);
        config()->set('cloudinary_media.videos_enabled', true);
    }

    public function test_public_upload_and_delivery_presets_are_bounded(): void
    {
        $storage = new FakeUnifiedMediaStorage;
        $path = $storage->publicImage(UploadedFile::fake()->image('card.jpg'), 'events');
        $this->assertTrue($storage->isCloudinary($path));
        $this->assertStringContainsString('res.cloudinary.com/example-cloud/', $storage->url($path));
        $this->assertStringContainsString('/image/upload/c_limit,w_1280,q_auto,f_auto/', $storage->url($path));
        $this->assertStringContainsString('/image/upload/c_limit,w_480,q_auto,f_auto/', $storage->url($path, 'thumb'));
        $this->assertDatabaseHas('cloudinary_media_assets', ['path' => $path, 'resource_type' => 'image']);
        $localPath = CloudinaryMediaAsset::where('path', $path)->value('local_path');
        Storage::disk('public')->assertExists($localPath);
        config()->set('cloudinary_media.images_enabled', false);
        $this->assertSame(Storage::disk('public')->url($localPath), $storage->url($path));
        config()->set('cloudinary_media.images_enabled', true);
        $storage->delete($path);
        $this->assertDatabaseHas('cloudinary_media_assets', ['path' => $path, 'status' => 'deleted']);
        Storage::disk('public')->assertMissing($localPath);
        $this->assertCount(1, $storage->deletions);
    }

    public function test_video_uses_chunks_and_exposes_one_poster_preset(): void
    {
        $storage = new FakeUnifiedMediaStorage;
        $file = tempnam(sys_get_temp_dir(), 'video-test-');
        file_put_contents($file, str_repeat('x', 11 * 1048576));
        try {
            $path = $storage->upload($file, 'video', 'events-preview');
            $this->assertSame(6 * 1048576, $storage->lastOptions['chunk_size']);
            $this->assertStringContainsString('/video/upload/so_1,c_limit,w_480,q_auto/', $storage->url($path, 'poster'));
            $this->assertStringEndsWith('.jpg', $storage->url($path, 'poster'));
        } finally {
            unlink($file);
        }
    }

    public function test_replacement_retires_only_the_old_cloudinary_asset(): void
    {
        $storage = new FakeUnifiedMediaStorage;
        $old = $storage->publicImage(UploadedFile::fake()->image('old.jpg'), 'categories');
        $new = $storage->publicImage(UploadedFile::fake()->image('new.jpg'), 'categories');
        $storage->deleteReplaced($old, $new);
        $this->assertDatabaseHas('cloudinary_media_assets', ['path' => $old, 'status' => 'deleted']);
        $this->assertDatabaseHas('cloudinary_media_assets', ['path' => $new]);
        $this->assertCount(1, $storage->deletions);
    }

    public function test_exhausted_credits_fall_back_only_for_public_images(): void
    {
        Storage::fake('public');
        Cache::forget('cloudinary:usage');
        $storage = new FakeUnifiedMediaStorage;
        $storage->creditPercent = 95;
        $path = $storage->publicImage(UploadedFile::fake()->image('fallback.jpg'), 'events');
        $this->assertFalse($storage->isCloudinary($path));
        Storage::disk('public')->assertExists($path);
        config()->set('cloudinary_media.allow_local_fallback', false);
        Cache::forget('cloudinary:usage');
        $this->expectException(RuntimeException::class);
        $storage->publicImage(UploadedFile::fake()->image('blocked.jpg'), 'events');
    }

    public function test_api_upload_failure_obeys_the_local_fallback_flag(): void
    {
        $storage = new FakeUnifiedMediaStorage;
        $storage->failUpload = true;
        $local = $storage->publicImage(UploadedFile::fake()->image('fallback.jpg'), 'events');
        $this->assertFalse($storage->isCloudinary($local));
        Storage::disk('public')->assertExists($local);

        config()->set('cloudinary_media.allow_local_fallback', false);
        try {
            $storage->publicImage(UploadedFile::fake()->image('blocked.jpg'), 'events');
            $this->fail('Expected Cloudinary API failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('Synthetic Cloudinary failure.', $e->getMessage());
        }
    }

    public function test_deleting_assets_does_not_reset_the_monthly_upload_budget(): void
    {
        config()->set('cloudinary_media.max_monthly_upload_mb', 1);
        $storage = new FakeUnifiedMediaStorage;
        $file = tempnam(sys_get_temp_dir(), 'budget-test-');
        file_put_contents($file, str_repeat('x', 700000));
        try {
            $path = $storage->upload($file, 'video', 'events-preview');
            $storage->delete($path);
            $this->assertDatabaseHas('cloudinary_media_assets', ['path' => $path, 'status' => 'deleted']);
            $this->expectException(RuntimeException::class);
            $storage->upload($file, 'video', 'events-preview');
        } finally {
            unlink($file);
        }
    }

    public function test_paid_original_stays_private_while_cloudinary_preview_streams(): void
    {
        Storage::fake('media');
        $owner = User::factory()->create(['is_active' => true]);
        $event = Events::create(['user_id' => $owner->id, 'title' => 'Film', 'slug' => 'film', 'is_active' => true]);
        $preview = 'cloudinary/video/scemory/events-preview/11111111-1111-4111-8111-111111111111.mp4';
        CloudinaryMediaAsset::create(['path' => $preview, 'public_id' => 'scemory/events-preview/11111111-1111-4111-8111-111111111111', 'resource_type' => 'video', 'format' => 'mp4', 'version' => 123]);
        $media = EventsImges::create(['event_id' => $event->id, 'type' => 'video', 'price' => 15, 'is_active' => true,
            'full_url' => 'videos/private.mp4', 'original_disk' => 'media', 'preview_url' => $preview, 'preview_disk' => 'cloudinary',
            'fallback_preview_url' => 'videos/watermarked.mp4']);
        Storage::disk('media')->put('videos/private.mp4', 'original-video');
        Storage::disk('media')->put('videos/watermarked.mp4', 'watermarked-video');
        $this->assertArrayNotHasKey('fallback_preview_url', $media->toArray());
        $this->get(route('media.preview', $media))->assertRedirectContains('res.cloudinary.com/example-cloud/video/upload/');
        config()->set('cloudinary_media.videos_enabled', false);
        $this->get(route('media.preview', $media))->assertOk();
        config()->set('cloudinary_media.videos_enabled', true);
        $this->getJson('/api/v1/download/'.$media->id)->assertUnauthorized();
        $buyer = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/download/'.$media->id)->assertForbidden();
        Entitlement::create(['user_id' => $buyer->id, 'media_id' => $media->id, 'source' => 'purchase', 'granted_at' => now()]);
        $this->get('/api/v1/download/'.$media->id)->assertOk();
        $event->update(['is_active' => false]);
        $this->get(route('media.preview', $media))->assertNotFound();
    }

    public function test_approval_promotes_only_a_preview_and_keeps_the_paid_original_private(): void
    {
        Storage::fake('media');
        $owner = User::factory()->create(['is_active' => true]);
        $event = Events::create(['user_id' => $owner->id, 'title' => 'Review', 'slug' => 'review', 'is_active' => true]);
        $media = EventsImges::create(['event_id' => $event->id, 'type' => 'image', 'price' => 10, 'is_active' => true,
            'full_url' => 'events/full/original.jpg', 'original_disk' => 'media',
            'preview_url' => 'events/preview/watermarked.jpg', 'preview_disk' => 'media']);
        Storage::disk('media')->put('events/full/original.jpg', 'original');
        Storage::disk('media')->put('events/preview/watermarked.jpg', 'preview');
        (new PromotePublishedMediaPreviews($event->id))->handle(new FakeUnifiedMediaStorage);
        $media->refresh();
        $this->assertSame('media', $media->original_disk);
        $this->assertSame('events/full/original.jpg', $media->full_url);
        $this->assertSame('cloudinary', $media->preview_disk);
        $this->assertSame('events/preview/watermarked.jpg', $media->fallback_preview_url);
        $this->assertDatabaseHas('cloudinary_media_assets', ['path' => $media->preview_url, 'resource_type' => 'image']);
        Storage::disk('media')->assertExists('events/full/original.jpg');
    }

    public function test_event_scoped_command_retries_a_published_local_preview(): void
    {
        Storage::fake('media');
        $owner = User::factory()->create(['is_active' => true]);
        $event = Events::create(['user_id' => $owner->id, 'title' => 'Retry', 'slug' => 'retry', 'is_active' => true]);
        $media = EventsImges::create(['event_id' => $event->id, 'type' => 'image', 'price' => 10, 'is_active' => true,
            'full_url' => 'events/full/private.jpg', 'original_disk' => 'media',
            'preview_url' => 'events/preview/public.jpg', 'preview_disk' => 'media']);
        Storage::disk('media')->put('events/full/private.jpg', 'private');
        Storage::disk('media')->put('events/preview/public.jpg', 'preview');
        app()->instance(UnifiedMediaStorage::class, new FakeUnifiedMediaStorage);

        $this->artisan('media:cloudinary-event', ['eventId' => $event->id, '--promote' => true])->assertExitCode(0);

        $media->refresh();
        $this->assertSame('cloudinary', $media->preview_disk);
        $this->assertSame('media', $media->original_disk);
        $this->assertSame('events/preview/public.jpg', $media->fallback_preview_url);
        Storage::disk('media')->assertExists('events/full/private.jpg');
    }
}

class FakeUnifiedMediaStorage extends UnifiedMediaStorage
{
    public array $lastOptions = [];
    public array $deletions = [];
    public int $creditPercent = 0;
    public bool $failUpload = false;

    protected function sendUpload(string $file, array $options): array
    {
        if ($this->failUpload) throw new RuntimeException('Synthetic Cloudinary failure.');
        $this->lastOptions = $options;
        return [
            'public_id' => $options['public_id'], 'asset_id' => 'asset-1',
            'resource_type' => $options['resource_type'], 'format' => $options['resource_type'] === 'video' ? 'mp4' : 'jpg',
            'version' => 123, 'bytes' => filesize($file), 'width' => 400, 'height' => 300,
            'duration' => $options['resource_type'] === 'video' ? 5 : null,
        ];
    }

    protected function sendDestroy(string $publicId, string $type): void
    {
        $this->deletions[] = [$publicId, $type];
    }

    protected function fetchUsage(): array
    {
        return ['credits' => ['usage' => 25 * $this->creditPercent / 100, 'limit' => 25]];
    }
}
