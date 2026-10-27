<?php

namespace Tests\Feature\Api;

use App\Mail\SupportNotification;
use App\Models\ContactMessage;
use App\Models\Contacts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportConversationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'name' => 'Support Customer', 'email' => 'customer@example.test',
            'subject' => 'Payment issue', 'message' => 'My payment could not be completed.',
            'submission_key' => (string) Str::uuid(),
        ];
    }

    public function test_authenticated_user_and_admin_exchange_messages_in_one_ticket(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);
        $payload = $this->payload();
        $created = $this->postJson('/api/v1/contacts/create', $payload)->assertOk()->json('data.ticket.id');
        $this->assertDatabaseHas('contacts', ['id' => $created, 'user_id' => $user->id, 'status' => 'open']);
        $this->assertDatabaseCount('contact_messages', 1);
        $this->postJson('/api/v1/contacts/create', $payload)->assertOk()->assertJsonPath('data.ticket.id', $created);
        $this->assertDatabaseCount('contacts', 1);
        $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonPath('data.data.0.id', $created);
        $this->getJson("/api/v1/support/tickets/{$created}")->assertOk()
            ->assertJsonPath('data.messages.data.0.message', $payload['message']);
        $userKey = (string) Str::uuid();
        $this->postJson("/api/v1/support/tickets/{$created}/messages", ['message' => 'Another detail', 'idempotency_key' => $userKey])->assertOk();
        $this->postJson("/api/v1/support/tickets/{$created}/messages", ['message' => 'Another detail', 'idempotency_key' => $userKey])->assertOk();
        $this->assertDatabaseCount('contact_messages', 2);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/support/tickets/{$created}")->assertNotFound();
        $this->postJson("/api/v1/support/tickets/{$created}/messages", ['message' => 'Intrusion', 'idempotency_key' => (string) Str::uuid()])->assertNotFound();
        $this->getJson('/api/v1/contacts')->assertUnauthorized();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/contacts?status=unread')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson("/api/v1/contacts/{$created}")->assertOk()->assertJsonCount(2, 'data.messages.data');
        $this->getJson('/api/v1/contacts?status=unread')->assertOk()->assertJsonPath('data.total', 0);
        Sanctum::actingAs($secondAdmin);
        $this->getJson('/api/v1/contacts?status=unread')->assertOk()->assertJsonPath('data.total', 1);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/contacts/respond/{$created}", ['message' => 'Please retry now.', 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->assertDatabaseHas('contacts', ['id' => $created, 'status' => 'pending']);
        $this->patchJson("/api/v1/contacts/{$created}/status", ['status' => 'resolved'])->assertOk();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonPath('data.data.0.unread_count', 1);
        $this->getJson("/api/v1/support/tickets/{$created}")->assertOk()->assertJsonCount(3, 'data.messages.data');
        $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonPath('data.data.0.unread_count', 0);
        $this->postJson("/api/v1/support/tickets/{$created}/messages", ['message' => 'It still fails.', 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->assertDatabaseHas('contacts', ['id' => $created, 'status' => 'open']);
        $this->assertSame(4, ContactMessage::where('contact_id', $created)->count());
        Mail::assertQueued(SupportNotification::class);
    }

    public function test_guest_access_requires_private_token_and_expires(): void
    {
        Mail::fake();
        $created = $this->postJson('/api/v1/contacts/create', $this->payload())->assertOk();
        $id = $created->json('data.ticket.id');
        $token = $created->json('data.access_token');
        $this->assertNotEmpty($token);
        $this->getJson("/api/v1/support/tickets/{$id}")->assertNotFound();
        $this->withHeader('X-Support-Access', $token)
            ->getJson("/api/v1/support/tickets/{$id}")->assertOk();
        $this->withHeader('X-Support-Access', $token)
            ->postJson("/api/v1/support/tickets/{$id}/messages", [
                'message' => 'Additional information', 'idempotency_key' => (string) Str::uuid(),
            ])->assertOk();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/contacts/respond/{$id}", [
            'message' => 'We are looking into it.', 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $this->withHeader('X-Support-Access', $token)
            ->getJson("/api/v1/support/tickets/{$id}")->assertOk()
            ->assertJsonPath('data.ticket.unread_count', 1)
            ->assertJsonCount(3, 'data.messages.data');
        Contacts::findOrFail($id)->update(['guest_access_expires_at' => now()->subMinute()]);
        $this->withHeader('X-Support-Access', $token)
            ->getJson("/api/v1/support/tickets/{$id}")->assertNotFound();
    }

    public function test_notification_queue_failure_does_not_turn_persisted_ticket_into_failure(): void
    {
        Mail::shouldReceive('to')->twice()->andThrow(new \RuntimeException('mail transport unavailable'));
        $this->postJson('/api/v1/contacts/create', $this->payload())->assertOk();
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('contact_messages', 1);
    }
}
