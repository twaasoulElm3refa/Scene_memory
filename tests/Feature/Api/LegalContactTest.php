<?php

namespace Tests\Feature\Api;

use App\Models\Footer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegalContactTest extends TestCase
{
    use RefreshDatabase;

    private function report(): array
    {
        return ['name' => 'Rights Owner', 'email' => 'owner@example.test', 'subject' => 'copyright', 'message' => 'Please review the content at https://scemory.com/ar/single_event/example'];
    }

    public function test_guest_can_submit_a_report_to_the_existing_inbox(): void
    {
        $this->postJson('/api/v1/contacts/create', $this->report())
            ->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('contacts', $this->report());
    }

    public function test_authenticated_non_admin_can_request_privacy_help(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->postJson('/api/v1/contacts/create', [...$this->report(), 'subject' => 'privacy'])
            ->assertOk();
    }

    public function test_report_validation_and_request_limits_apply(): void
    {
        $this->postJson('/api/v1/contacts/create', ['email' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
        $this->postJson('/api/v1/contacts/create', [...$this->report(), 'message' => str_repeat('x', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_public_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/contacts/create', $this->report())->assertOk();
        }
        $this->postJson('/api/v1/contacts/create', $this->report())->assertStatus(429);
    }

    public function test_inbox_and_settings_remain_protected(): void
    {
        $this->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->getJson('/api/v1/contacts/1')->assertUnauthorized();
        $this->postJson('/api/v1/contacts/respond/1', [])->assertUnauthorized();
        $this->postJson('/api/v1/footer/update', [])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->postJson('/api/v1/footer/update', [])->assertUnauthorized();
    }

    public function test_footer_exposes_only_public_destination_fields(): void
    {
        Footer::create(['logo' => 'private-path.png', 'facebook' => 'https://www.facebook.com/scemory', 'twitter' => 'https://x.com/scemory']);
        $response = $this->getJson('/api/v1/footer/links')->assertOk();
        $response->assertJsonPath('data.facebook', 'https://www.facebook.com/scemory');
        $this->assertSame(['facebook', 'twitter', 'instagram', 'google_play', 'app_store'], array_keys($response->json('data')));
        $response->assertJsonMissingPath('data.logo')->assertJsonMissingPath('data.id');
    }
}
