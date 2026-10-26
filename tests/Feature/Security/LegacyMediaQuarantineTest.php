<?php

namespace Tests\Feature\Security;

use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacyMediaQuarantineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media');
        Storage::fake('legacy-quarantine');
        Bus::fake();
    }

    public function test_verified_legacy_original_leaves_the_webroot_and_buyers_keep_access(): void
    {
        [$owner, $media] = $this->legacyMedia();
        $plan = $this->planAndApply();

        $this->artisan('media:legacy-quarantine', ['manifest' => $plan])->assertSuccessful();
        Storage::disk('public')->assertExists('events/full/original.jpg');
        $this->assertSame('planned', $this->latestQuarantine($plan)['entries'][0]['status']);

        $this->artisan('media:legacy-quarantine', ['manifest' => $plan, '--execute' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('events/full/original.jpg');
        // A published item keeps its public watermarked preview.
        Storage::disk('public')->assertExists('events/preview/sample.jpg');
        $entry = $this->latestQuarantine($plan)['entries'][0];
        $this->assertSame('quarantined', $entry['status']);
        $this->assertSame('legacy-original', Storage::disk('legacy-quarantine')->get($entry['quarantine_path']));

        $buyer = User::factory()->create(['is_active' => true]);
        Entitlement::create(['user_id' => $buyer->id, 'media_id' => $media->id, 'source' => 'purchase', 'granted_at' => now()]);
        Sanctum::actingAs($buyer);
        $this->assertSame('legacy-original', $this->get('/api/v1/download/'.$media->id)->assertOk()->streamedContent());
        Sanctum::actingAs(User::factory()->create(['is_active' => true]));
        $this->get('/api/v1/download/'.$media->id)->assertForbidden();
        Sanctum::actingAs($owner);
        $this->get('/api/v1/download/'.$media->id)->assertOk();
    }

    public function test_unpublished_media_also_quarantines_its_legacy_preview(): void
    {
        $this->legacyMedia(published: false);
        $plan = $this->planAndApply();

        $this->artisan('media:legacy-quarantine', ['manifest' => $plan, '--execute' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing('events/full/original.jpg');
        Storage::disk('public')->assertMissing('events/preview/sample.jpg');
        $this->assertCount(2, collect($this->latestQuarantine($plan)['entries'])->where('status', 'quarantined'));
    }

    public function test_paths_shared_with_public_media_and_unverified_copies_are_never_moved(): void
    {
        [, $media] = $this->legacyMedia();
        $plan = $this->planAndApply();
        // Intentionally public media that started using the same file after planning.
        EventsImges::create(['event_id' => $media->event_id, 'type' => 'image', 'is_active' => 1, 'price' => 0,
            'full_url' => 'events/full/original.jpg', 'preview_url' => 'free.jpg', 'original_disk' => 'public', 'preview_disk' => 'public']);

        $this->artisan('media:legacy-quarantine', ['manifest' => $plan, '--execute' => true])->assertSuccessful();
        Storage::disk('public')->assertExists('events/full/original.jpg');
        $this->assertSame('shared_public_path', $this->latestQuarantine($plan)['entries'][0]['reason']);

        EventsImges::where('preview_url', 'free.jpg')->delete();
        Storage::disk('media')->put($media->fresh()->full_url, 'tampered');
        $this->artisan('media:legacy-quarantine', ['manifest' => $plan, '--execute' => true])->assertSuccessful();
        Storage::disk('public')->assertExists('events/full/original.jpg');
        $this->assertSame('private_copy_unverified', $this->latestQuarantine($plan)['entries'][0]['reason']);
    }

    public function test_rollback_restores_files_and_optionally_references(): void
    {
        [, $media] = $this->legacyMedia(published: false);
        $plan = $this->planAndApply();
        $this->artisan('media:legacy-quarantine', ['manifest' => $plan, '--execute' => true])->assertSuccessful();
        $quarantine = $this->latestQuarantineName($plan);

        $this->artisan('media:legacy-quarantine', ['manifest' => $quarantine, '--rollback' => true, '--references' => true])->assertSuccessful();

        $this->assertSame('legacy-original', Storage::disk('public')->get('events/full/original.jpg'));
        $this->assertSame('legacy-preview', Storage::disk('public')->get('events/preview/sample.jpg'));
        $restored = $media->fresh();
        $this->assertSame('public', $restored->original_disk);
        $this->assertSame('events/full/original.jpg', $restored->getRawOriginal('full_url'));
        // The quarantine copy is kept; a second rollback is a no-op.
        $this->artisan('media:legacy-quarantine', ['manifest' => $quarantine, '--rollback' => true])->assertSuccessful();
    }

    public function test_a_plan_manifest_is_required_and_rollback_needs_a_quarantine_manifest(): void
    {
        $this->artisan('media:legacy-quarantine', ['manifest' => '../.env'])->assertFailed();
        $this->legacyMedia();
        $plan = $this->planAndApply();
        $this->artisan('media:legacy-quarantine', ['manifest' => $plan, '--rollback' => true])->assertFailed();
        Storage::disk('public')->assertExists('events/full/original.jpg');
    }

    public function test_signed_original_links_cannot_be_retargeted_or_reused_after_expiry(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $event = Events::create(['title' => 'Signed', 'slug' => 'signed-'.uniqid(), 'user_id' => $owner->id, 'is_active' => true]);
        $media = EventsImges::create(['event_id' => $event->id, 'type' => 'image', 'is_active' => true, 'price' => 10,
            'full_url' => 'events/full/paid.jpg', 'preview_url' => 'events/preview/paid.jpg', 'original_disk' => 'media', 'preview_disk' => 'media']);
        Storage::disk('media')->put('events/full/paid.jpg', 'paid-original');
        Storage::disk('media')->put('events/preview/paid.jpg', 'paid-preview');
        $buyer = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        Entitlement::create(['user_id' => $buyer->id, 'media_id' => $media->id, 'source' => 'purchase', 'granted_at' => now()]);

        $url = URL::temporarySignedRoute('media.purchased-original', now()->addMinutes(5), ['media' => $media->id, 'user' => $buyer->id]);
        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->get(str_replace('user='.$buyer->id, 'user='.$other->id, $url))->assertForbidden();
        $this->get(URL::temporarySignedRoute('media.purchased-original', now()->addMinutes(5), ['media' => $media->id, 'user' => $other->id]))->assertForbidden();
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();

        $buyer->update(['is_active' => false]);
        $this->travelBack();
        $this->get(URL::temporarySignedRoute('media.purchased-original', now()->addMinutes(5), ['media' => $media->id, 'user' => $buyer->id]))->assertForbidden();
    }

    private function legacyMedia(bool $published = true): array
    {
        $owner = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $event = Events::create(['title' => 'Legacy', 'slug' => 'legacy-'.uniqid(), 'user_id' => $owner->id, 'is_active' => $published]);
        $media = EventsImges::create(['event_id' => $event->id, 'type' => 'image', 'is_active' => true, 'price' => 10,
            'full_url' => 'events/full/original.jpg', 'preview_url' => 'events/preview/sample.jpg', 'original_disk' => 'public', 'preview_disk' => 'public']);
        Storage::disk('public')->put('events/full/original.jpg', 'legacy-original');
        Storage::disk('public')->put('events/preview/sample.jpg', 'legacy-preview');

        return [$owner, $media];
    }

    private function planAndApply(): string
    {
        $name = 'fixture-quarantine-'.uniqid().'.json';
        $this->artisan('media:security-plan', ['--copy' => true, '--apply' => true, '--acknowledge-edge-blocking' => true, '--output' => $name])->assertSuccessful();

        return $name;
    }

    private function latestQuarantineName(string $plan): string
    {
        // Names carry a microsecond timestamp, so lexical order is creation order.
        $files = glob(storage_path('app/security-audit/quarantine-'.pathinfo($plan, PATHINFO_FILENAME).'-*.json'));
        sort($files);

        return basename(end($files));
    }

    private function latestQuarantine(string $plan): array
    {
        return json_decode(file_get_contents(storage_path('app/security-audit/'.$this->latestQuarantineName($plan))), true);
    }
}
