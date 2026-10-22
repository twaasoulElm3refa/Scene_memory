<?php

namespace Tests\Feature\Security;

use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use App\Services\MediaAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MediaProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media');
        Bus::fake();
    }

    private function fixture(bool $published = true, int $price = 10): array
    {
        $owner = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $event = Events::create(['title' => 'Fixture', 'slug' => 'fixture-'.uniqid(), 'user_id' => $owner->id, 'is_active' => $published]);
        $media = EventsImges::create(['event_id' => $event->id, 'type' => 'image', 'is_active' => true, 'price' => $price, 'full_url' => 'events/full/original.jpg', 'preview_url' => 'events/preview/sample.jpg', 'original_disk' => 'media', 'preview_disk' => 'media']);
        Storage::disk('media')->put($media->full_url, 'local-paid-original');
        Storage::disk('media')->put($media->preview_url, 'local-public-preview');

        return [$owner, $event, $media];
    }

    public function test_guest_sees_a_preview_but_cannot_download_a_paid_original(): void
    {
        [, , $media] = $this->fixture();
        $this->get(app(MediaAccessService::class)->previewUrl($media))->assertOk();
        $this->getJson('/api/v1/download/'.$media->id)->assertUnauthorized();
        Storage::disk('public')->assertMissing($media->full_url);
        Storage::disk('public')->assertMissing($media->preview_url);
    }

    public function test_owner_seller_and_admin_can_download_without_buying_their_own_work(): void
    {
        [$owner, , $media] = $this->fixture(false);
        Sanctum::actingAs($owner);
        $this->get('/api/v1/download/'.$media->id)->assertOk();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/api/v1/download/'.$media->id)->assertOk();
    }

    public function test_nonbuyer_and_wrong_product_are_denied_and_revoked_entitlement_stops_access(): void
    {
        [, , $media] = $this->fixture();
        $buyer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/download/'.$media->id)->assertForbidden();
        $entitlement = Entitlement::create(['user_id' => $buyer->id, 'media_id' => $media->id, 'source' => 'purchase', 'granted_at' => now()]);
        $this->get('/api/v1/download/'.$media->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        [, , $other] = $this->fixture();
        $this->getJson('/api/v1/download/'.$other->id)->assertForbidden();
        $entitlement->delete();
        $this->getJson('/api/v1/download/'.$media->id)->assertForbidden();
    }

    public function test_pending_content_is_not_public_and_expiring_review_urls_are_enforced(): void
    {
        [, $event, $media] = $this->fixture(false);
        $this->getJson('/api/v1/events/'.$event->slug.'/single/get')->assertNotFound();
        $this->getJson('/api/v1/collections/'.$event->id)->assertNotFound();
        $this->getJson(app(MediaAccessService::class)->previewUrl($media))->assertNotFound();
        $signed = app(MediaAccessService::class)->reviewOriginalUrl($media);
        $this->get($signed)->assertOk();
        $this->getJson(route('media.review-original', ['media' => $media->id]))->assertForbidden();
        $this->travel(6)->minutes();
        $this->getJson($signed)->assertForbidden();
    }

    public function test_public_catalog_and_cart_preserve_display_contract_without_original_paths(): void
    {
        [, $event, $media] = $this->fixture();
        $response = $this->getJson('/api/v1/events/'.$event->slug.'/single/get')->assertOk();
        $this->assertStringNotContainsString('events/full/original.jpg', $response->getContent());
        $this->assertStringContainsString('/media/'.$media->id.'/preview', $response->json('data.images.0.full_url'));
        $buyer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/cart/addToCart/'.$media->id)->assertOk();
        $cart = $this->getJson('/api/v1/cart/get')->assertOk();
        $this->assertStringNotContainsString('events/full/original.jpg', $cart->getContent());
        $this->assertStringContainsString('/media/'.$media->id.'/preview', $cart->json('data.0.full_url'));
    }

    public function test_paid_legacy_original_alias_is_never_presented_as_a_safe_preview(): void
    {
        [, , $media] = $this->fixture();
        $media->update(['preview_url' => $media->full_url]);
        $this->assertNull(app(MediaAccessService::class)->previewUrl($media));
        $this->getJson(route('media.preview', ['media' => $media->id]))->assertNotFound();
    }

    public function test_path_traversal_and_symlinks_cannot_escape_the_private_disk(): void
    {
        [$owner, , $media] = $this->fixture();
        Sanctum::actingAs($owner);
        foreach (['../outside.txt', 'safe/../../outside.txt', 'https://example.test/file', '%2e%2e/secret', 'safe/./file', 'C:\\secret'] as $path) {
            $media->update(['full_url' => $path]);
            $this->getJson('/api/v1/download/'.$media->id)->assertNotFound();
        }
        $outside = tempnam(sys_get_temp_dir(), 'scemory-local-');
        file_put_contents($outside, 'local-fixture-secret');
        $link = Storage::disk('media')->path('escape.txt');
        try {
            symlink($outside, $link);
            $media->update(['full_url' => 'escape.txt']);
            $this->getJson('/api/v1/download/'.$media->id)->assertNotFound();
        } finally {
            unlink($link);
            unlink($outside);
        }
    }

    public function test_new_uploads_are_private_and_a_direct_static_url_is_not_a_controller_test(): void
    {
        [$owner, $event] = $this->fixture();
        Sanctum::actingAs($owner);
        $upload = UploadedFile::fake()->image('../../untrusted.php.jpg', 30, 30);
        $hash = hash_file('sha256', $upload->getRealPath());
        $this->postJson('/api/v1/media-request/upload/'.$event->id, ['url' => [$upload]])->assertOk();
        $media = EventsImges::latest('id')->first();
        $this->assertSame('media', $media->original_disk);
        $this->assertTrue(Storage::disk('media')->exists($media->full_url));
        $this->assertSame($hash, hash_file('sha256', Storage::disk('media')->path($media->full_url)));
        $this->assertFalse(Storage::disk('public')->exists($media->full_url));

        // A real HTTP static server serves ONLY the fake public root, not Laravel.
        $server = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        fclose($server);
        $root = sys_get_temp_dir().'/scemory-static-'.uniqid();
        mkdir($root, 0700);
        symlink(Storage::disk('public')->path(''), $root.'/storage');
        Storage::disk('public')->put('events/full/legacy-fixture.jpg', 'legacy-public-fixture');
        $process = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $root]);
        $process->start();
        try {
            for ($i = 0; $i < 50; $i++) {
                $socket = @fsockopen('127.0.0.1', $port);
                if ($socket) {
                    fclose($socket);
                    break;
                }
                usleep(20000);
            }
            $context = stream_context_create(['http' => ['ignore_errors' => true]]);
            $legacy = file_get_contents('http://127.0.0.1:'.$port.'/storage/events/full/legacy-fixture.jpg', false, $context);
            $this->assertSame('legacy-public-fixture', $legacy); // Legacy exposure is explicit.
            $new = file_get_contents('http://127.0.0.1:'.$port.'/storage/'.$media->full_url, false, $context);
            $this->assertStringContainsString('404', $http_response_header[0]);
            $this->assertNotSame(Storage::disk('media')->get($media->full_url), $new);
        } finally {
            $process->stop();
            unlink($root.'/storage');
            rmdir($root);
        }
    }

    public function test_migration_is_copy_only_verified_and_cannot_apply_without_operational_acknowledgment(): void
    {
        [, , $media] = $this->fixture();
        $media->update(['original_disk' => 'public', 'preview_disk' => 'public']);
        Storage::disk('public')->put($media->full_url, 'legacy-original');
        Storage::disk('public')->put($media->preview_url, 'legacy-preview');
        $this->artisan('media:security-plan', ['--apply' => true])->assertFailed();
        $name = 'fixture-'.uniqid().'.json';
        $this->artisan('media:security-plan', ['--copy' => true, '--output' => $name])->assertSuccessful();
        $manifest = json_decode(file_get_contents(storage_path('app/security-audit/'.$name)), true);
        $row = collect($manifest['rows'])->firstWhere('media_id', $media->id);
        $this->assertSame('copied_verified', $row['status']);
        $this->assertSame(hash('sha256', 'legacy-original'), $row['sha256']);
        $this->assertSame('public', $media->fresh()->original_disk);
        Storage::disk('public')->assertExists($media->full_url);
    }

    public function test_unpublication_invalidates_a_warm_public_response(): void
    {
        [, $event, $media] = $this->fixture();
        $url = '/api/v1/events/'.$event->slug.'/single/get';
        $this->getJson($url)->assertOk();
        $event->update(['is_active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->getJson(route('media.preview', ['media' => $media->id]))->assertNotFound();
        $this->getJson('/api/v1/comments/'.$event->slug)->assertNotFound();
    }

    public function test_normalized_original_alias_and_internal_symlink_are_not_preview_bypasses(): void
    {
        [, , $media] = $this->fixture();
        $media->update(['preview_url' => '/storage/'.$media->full_url]);
        $this->getJson(route('media.preview', ['media' => $media->id]))->assertNotFound();
        $path = 'events/full/alias.jpg';
        symlink(Storage::disk('media')->path($media->full_url), Storage::disk('media')->path($path));
        $media->update(['preview_url' => $path]);
        $this->getJson(route('media.preview', ['media' => $media->id]))->assertNotFound();
    }

    public function test_migration_applies_verified_references_and_detects_corruption_on_a_rerun(): void
    {
        [$owner, , $media] = $this->fixture();
        $media->update(['original_disk' => 'public', 'preview_disk' => 'public']);
        Storage::disk('public')->put($media->full_url, 'legacy-original');
        Storage::disk('public')->put($media->preview_url, 'legacy-preview');
        $target = 'legacy/'.$media->id.'/original-'.$media->id.'.jpg';
        Storage::disk('media')->put($target, 'damaged-fixture');
        $name = 'fixture-corrupt-'.uniqid().'.json';
        $this->artisan('media:security-plan', ['--copy' => true, '--output' => $name])->assertFailed();
        $this->assertSame('public', $media->fresh()->original_disk);
        // Repair only the file this test created; never overwrite an operator's copy.
        Storage::disk('media')->put($target, 'legacy-original');
        $this->artisan('media:security-plan', ['--copy' => true, '--apply' => true, '--acknowledge-edge-blocking' => true, '--output' => 'fixture-apply-'.uniqid().'.json'])->assertSuccessful();
        $this->assertSame('media', $media->fresh()->original_disk);
        Storage::disk('public')->assertExists('events/full/original.jpg');
        Sanctum::actingAs($owner);
        $this->get('/api/v1/download/'.$media->id)->assertOk();
    }

    public function test_migration_refuses_to_block_a_path_shared_with_intentionally_public_media(): void
    {
        [, $event, $restricted] = $this->fixture();
        $restricted->update(['original_disk' => 'public', 'preview_disk' => 'public']);
        EventsImges::create(['event_id' => $event->id, 'type' => 'image', 'is_active' => 1, 'price' => 0, 'full_url' => $restricted->full_url, 'preview_url' => 'free-preview.jpg', 'original_disk' => 'public']);
        $name = 'fixture-shared-'.uniqid().'.json';
        $this->artisan('media:security-plan', ['--copy' => true, '--output' => $name])->assertFailed();
        $manifest = json_decode(file_get_contents(storage_path('app/security-audit/'.$name)), true);
        $row = collect($manifest['rows'])->firstWhere('media_id', $restricted->id);
        $this->assertSame('shared_public_path', $row['requires_manual_review']);
        $this->assertSame('public', $restricted->fresh()->original_disk);
    }
}
