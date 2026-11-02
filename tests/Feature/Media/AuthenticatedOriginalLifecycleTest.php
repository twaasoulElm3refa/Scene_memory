<?php

namespace Tests\Feature\Media;

use App\Models\CloudinaryMediaAsset;
use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use App\Services\IndependentMediaBackup;
use App\Services\UnifiedMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticatedOriginalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private LifecycleCloudStorage $cloud;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Storage::fake('s3');
        config()->set('filesystems.disks.s3.bucket', 'test-independent-backup');
        config()->set('cloudinary_media.backup_disk', 's3');
        config()->set('cloudinary_media.backup_prefix', 'test-originals');
        config()->set('cloudinary_media.temp_original_cleanup_enabled', false);
        config()->set('cloudinary_media.require_verified_independent_backup', true);
        $this->cloud = new LifecycleCloudStorage;
        app()->instance(UnifiedMediaStorage::class, $this->cloud);
    }

    public function test_backup_is_private_integrity_checked_and_restorable(): void
    {
        [$media, $asset] = $this->original();
        $backup = app(IndependentMediaBackup::class);
        $this->assertTrue($backup->backUp($media, $asset));
        $asset->refresh();
        $this->assertSame('verified', $asset->backup_status);
        $this->assertTrue($backup->verify($asset));
        $this->assertSame('private', Storage::disk('s3')->visibility($asset->backup_key));
        $this->assertTrue($backup->restorationCheck($asset));
        Storage::disk('s3')->put($asset->backup_key, 'tampered');
        $this->assertFalse($backup->verify($asset));
    }

    public function test_cleanup_requires_flag_backup_cloud_verification_and_72_hours(): void
    {
        [$media, $asset] = $this->original();
        $preview = $media->preview_url;
        $original = $media->local_original_path;
        $this->artisan('media:cleanup-originals')->assertExitCode(1);
        Storage::disk('media')->assertExists($original);

        config()->set('cloudinary_media.temp_original_cleanup_enabled', true);
        $this->artisan('media:cleanup-originals')->assertExitCode(0);
        $this->assertSame('retention_window', $asset->fresh()->cleanup_block_reason);
        app(IndependentMediaBackup::class)->backUp($media, $asset);
        $this->travel(73)->hours();
        $this->artisan('media:cleanup-originals', ['--dry-run' => true])->assertExitCode(0);
        Storage::disk('media')->assertExists($original);
        $this->cloud->retrievable = false;
        $this->artisan('media:cleanup-originals')->assertExitCode(0);
        $this->assertSame('cloudinary_unverified', $asset->fresh()->cleanup_block_reason);
        Storage::disk('media')->assertExists($original);
        $this->cloud->retrievable = true;
        $this->artisan('media:cleanup-originals')->assertExitCode(0);
        $this->assertSame('completed', $asset->fresh()->cleanup_status);
        Storage::disk('media')->assertMissing($original);
        Storage::disk('media')->assertExists($preview);
        $this->artisan('media:cleanup-originals')->assertExitCode(0);
    }

    public function test_cleanup_keeps_unverified_backup_and_historical_originals(): void
    {
        [$media, $asset] = $this->original();
        config()->set('cloudinary_media.temp_original_cleanup_enabled', true);
        $this->travel(73)->hours();
        $this->artisan('media:cleanup-originals')->assertExitCode(0);
        $this->assertSame('backup_unverified', $asset->fresh()->cleanup_block_reason);
        Storage::disk('media')->assertExists($media->local_original_path);
        Storage::disk('media')->put('events/full/historical.jpg', 'historical');
        Storage::disk('media')->assertExists('events/full/historical.jpg');
    }

    public function test_cleanup_does_not_race_an_active_media_operation(): void
    {
        [$media, $asset] = $this->original();
        app(IndependentMediaBackup::class)->backUp($media, $asset);
        config()->set('cloudinary_media.temp_original_cleanup_enabled', true);
        $this->travel(73)->hours();
        $lock = Cache::lock('media-original:'.$media->id, 60);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('media:cleanup-originals')->assertExitCode(0);
            $this->assertSame('processing_lock', $asset->fresh()->cleanup_block_reason);
            Storage::disk('media')->assertExists($media->local_original_path);
        } finally {
            $lock->release();
        }
    }

    public function test_bounded_cleanup_rotates_past_a_blocked_asset(): void
    {
        [$blockedMedia, $blockedAsset] = $this->original();
        [$eligibleMedia, $eligibleAsset] = $this->original();
        app(IndependentMediaBackup::class)->backUp($eligibleMedia, $eligibleAsset);
        config()->set('cloudinary_media.temp_original_cleanup_enabled', true);
        config()->set('cloudinary_media.cleanup_batch_limit', 1);
        $this->travel(73)->hours();
        $this->artisan('media:cleanup-originals', ['--limit' => 1])->assertExitCode(0);
        $this->assertSame('backup_unverified', $blockedAsset->fresh()->cleanup_block_reason);
        $this->travel(1)->seconds();
        $this->artisan('media:cleanup-originals', ['--limit' => 1])->assertExitCode(0);
        $this->assertSame('completed', $eligibleAsset->fresh()->cleanup_status);
        Storage::disk('media')->assertExists($blockedMedia->local_original_path);
        Storage::disk('media')->assertMissing($eligibleMedia->local_original_path);
    }

    public function test_paid_original_requires_entitlement_and_downloads_clean_bytes(): void
    {
        [$media] = $this->original();
        $buyer = User::factory()->create(['is_active' => true]);
        $stranger = User::factory()->create(['is_active' => true]);
        $this->getJson('/api/v1/download/'.$media->id)->assertUnauthorized();
        $this->get('/api/v1/download/'.$media->id)->assertUnauthorized();
        Sanctum::actingAs($stranger);
        $this->getJson('/api/v1/download/'.$media->id)->assertForbidden();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/download/'.$media->id)->assertForbidden();
        $grant = Entitlement::create(['user_id' => $buyer->id, 'media_id' => $media->id,
            'source' => 'purchase', 'granted_at' => now()]);
        $response = $this->get('/api/v1/download/'.$media->id)->assertOk();
        $this->assertSame('clean-original-pixels', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $grant->delete();
        $this->getJson('/api/v1/download/'.$media->id)->assertForbidden();
    }

    public function test_cloudinary_outage_uses_only_the_retained_private_original(): void
    {
        [$media] = $this->original();
        $buyer = User::factory()->create(['is_active' => true]);
        Entitlement::create(['user_id' => $buyer->id, 'media_id' => $media->id,
            'source' => 'purchase', 'granted_at' => now()]);
        Sanctum::actingAs($buyer);
        $this->cloud->retrievable = false;
        $response = $this->get('/api/v1/download/'.$media->id)->assertOk();
        $this->assertSame('clean-original-pixels', file_get_contents($response->baseResponse->getFile()->getPathname()));
        Storage::disk('media')->delete($media->local_original_path);
        $this->getJson('/api/v1/download/'.$media->id)->assertStatus(503);
    }

    public function test_event_image_deletion_preserves_a_purchased_original(): void
    {
        [$media, $asset] = $this->original();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $buyer = User::factory()->create(['is_active' => true]);
        $grant = Entitlement::create(['user_id' => $buyer->id, 'media_id' => $media->id,
            'source' => 'purchase', 'granted_at' => now()]);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/event-images/'.$media->id.'/delete')->assertStatus(409);
        $this->assertDatabaseHas('events_images', ['id' => $media->id]);
        $this->assertDatabaseHas('cloudinary_media_assets', ['id' => $asset->id, 'status' => 'ready']);
        $grant->delete();
        $this->deleteJson('/api/v1/event-images/'.$media->id.'/delete')->assertOk();
        $this->assertDatabaseMissing('events_images', ['id' => $media->id]);
        Storage::disk('media')->assertExists($media->local_original_path);
    }

    private function original(): array
    {
        $owner = User::factory()->create(['is_active' => true]);
        $event = Events::create(['user_id' => $owner->id, 'title' => 'Image', 'slug' => 'lifecycle-'.$owner->id, 'is_active' => true]);
        $fingerprint = hash('sha256', 'lifecycle-'.$owner->id);
        $original = 'events/full/'.$fingerprint.'.png';
        $preview = 'events/preview/'.$fingerprint.'.jpg';
        $bytes = 'clean-original-pixels';
        Storage::disk('media')->put($original, $bytes);
        Storage::disk('media')->put($preview, 'blurred-watermarked');
        $publicId = 'scemory/events-original/'.Str::uuid();
        $path = 'cloudinary/image/'.$publicId.'.png';
        $media = EventsImges::create(['event_id' => $event->id, 'source_fingerprint' => $fingerprint,
            'type' => 'image', 'price' => 10, 'is_active' => true, 'full_url' => $path, 'original_disk' => 'cloudinary',
            'preview_url' => $preview, 'preview_disk' => 'media', 'local_original_path' => $original, 'media_status' => 'ready']);
        $asset = CloudinaryMediaAsset::create(['path' => $path, 'event_media_id' => $media->id,
            'public_id' => $publicId, 'asset_id' => 'asset-test-'.$owner->id,
            'resource_type' => 'image', 'delivery_type' => 'authenticated', 'format' => 'png',
            'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'uploaded_at' => now(),
            'status' => 'ready', 'backup_status' => 'pending', 'cleanup_status' => 'retained']);
        return [$media, $asset];
    }
}

class LifecycleCloudStorage extends UnifiedMediaStorage
{
    public bool $retrievable = true;

    public function verifyAuthenticatedOriginal(CloudinaryMediaAsset $asset): bool
    {
        return $this->retrievable;
    }

    public function authenticatedBytes(CloudinaryMediaAsset $asset): string
    {
        if (! $this->retrievable) throw new \RuntimeException('Synthetic Cloudinary outage.');
        return 'clean-original-pixels';
    }
}
