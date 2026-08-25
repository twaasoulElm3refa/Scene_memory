<?php

namespace Tests\Feature\Api;

use App\Mail\SpecialCoverageApprovedMail;
use App\Mail\SpecialCoverageRejectedMail;
use App\Models\SpecialCoverageRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecialCoverageRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_submit_request(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/special-coverage-requests', [
            'event_name' => 'AI Conference Riyadh',
            'event_description' => 'Special technology event.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', SpecialCoverageRequest::STATUS_PENDING);

        $this->assertDatabaseHas('special_coverage_requests', [
            'user_id' => $user->id,
            'event_name' => 'AI Conference Riyadh',
            'event_description' => 'Special technology event.',
            'status' => SpecialCoverageRequest::STATUS_PENDING,
        ]);
    }

    public function test_guest_cannot_submit_request(): void
    {
        $this->postJson('/api/v1/special-coverage-requests', [
            'event_name' => 'Guest Event',
            'event_description' => 'Should not be saved.',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('special_coverage_requests', 0);
    }

    public function test_user_cannot_spoof_another_user_id(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/special-coverage-requests', [
            'user_id' => $otherUser->id,
            'status' => SpecialCoverageRequest::STATUS_APPROVED,
            'reviewed_by' => $otherUser->id,
            'reviewed_at' => now()->toISOString(),
            'rejection_reason' => 'spoof',
            'event_name' => 'Spoofed Event',
            'event_description' => 'This must belong to the authenticated user.',
        ])->assertOk();

        $request = SpecialCoverageRequest::query()->firstOrFail();

        $this->assertSame($user->id, $request->user_id);
        $this->assertSame(SpecialCoverageRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->reviewed_by);
        $this->assertNull($request->reviewed_at);
        $this->assertNull($request->rejection_reason);
    }

    public function test_admin_can_list_and_view_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = SpecialCoverageRequest::query()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'event_name' => 'Coverage Needed',
            'event_description' => 'Details.',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/special-coverage-requests')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $request->id);

        $this->getJson("/api/v1/admin/special-coverage-requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.user.email', $request->user->email);
    }

    public function test_normal_user_cannot_access_admin_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/v1/admin/special-coverage-requests')
            ->assertUnauthorized();
    }

    public function test_admin_can_approve_pending_request_and_email_user(): void
    {
        Mail::fake();
        [$admin, $request] = $this->pendingRequest();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', SpecialCoverageRequest::STATUS_APPROVED)
            ->assertJsonPath('data.reviewed_by', $admin->id);

        $request->refresh();

        $this->assertSame(SpecialCoverageRequest::STATUS_APPROVED, $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
        $this->assertNull($request->rejection_reason);

        Mail::assertQueued(SpecialCoverageApprovedMail::class, function (SpecialCoverageApprovedMail $mail) use ($request) {
            return $mail->hasTo($request->user->email)
                && $mail->request->id === $request->id;
        });
    }

    public function test_admin_can_reject_with_required_reason_and_email_user(): void
    {
        Mail::fake();
        [$admin, $request] = $this->pendingRequest();
        $reason = 'The event date has already passed.';
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/reject")
            ->assertStatus(422);

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/reject", [
            'reason' => $reason,
        ])->assertOk()
            ->assertJsonPath('data.status', SpecialCoverageRequest::STATUS_REJECTED)
            ->assertJsonPath('data.rejection_reason', $reason)
            ->assertJsonPath('data.reviewed_by', $admin->id);

        $request->refresh();

        $this->assertSame(SpecialCoverageRequest::STATUS_REJECTED, $request->status);
        $this->assertSame($reason, $request->rejection_reason);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);

        Mail::assertQueued(SpecialCoverageRejectedMail::class, function (SpecialCoverageRejectedMail $mail) use ($request, $reason) {
            return $mail->hasTo($request->user->email)
                && $mail->request->id === $request->id
                && $mail->request->rejection_reason === $reason;
        });
    }

    public function test_processed_request_cannot_transition_again_or_send_duplicate_email(): void
    {
        Mail::fake();
        [$admin, $request] = $this->pendingRequest();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/approve")
            ->assertOk();

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/reject", [
            'reason' => 'Changed my mind.',
        ])->assertStatus(409);

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/approve")
            ->assertStatus(409);

        $this->assertSame(SpecialCoverageRequest::STATUS_APPROVED, $request->fresh()->status);
        Mail::assertQueued(SpecialCoverageApprovedMail::class, 1);
        Mail::assertNotQueued(SpecialCoverageRejectedMail::class);
    }

    public function test_rejected_request_cannot_be_approved_later(): void
    {
        Mail::fake();
        [$admin, $request] = $this->pendingRequest();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/reject", [
            'reason' => 'Not suitable.',
        ])->assertOk();

        $this->postJson("/api/v1/admin/special-coverage-requests/{$request->id}/approve")
            ->assertStatus(409);

        $this->assertSame(SpecialCoverageRequest::STATUS_REJECTED, $request->fresh()->status);
        Mail::assertQueued(SpecialCoverageRejectedMail::class, 1);
        Mail::assertNotQueued(SpecialCoverageApprovedMail::class);
    }

    public function test_invalid_request_id_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/v1/admin/special-coverage-requests/999999')
            ->assertNotFound();

        $this->postJson('/api/v1/admin/special-coverage-requests/999999/approve')
            ->assertNotFound();
    }

    /** @return array{0: User, 1: SpecialCoverageRequest} */
    private function pendingRequest(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'user',
            'email' => 'coverage-user@example.com',
        ]);

        $request = SpecialCoverageRequest::query()->create([
            'user_id' => $user->id,
            'event_name' => 'AI Conference Riyadh',
            'event_description' => 'Special technology event.',
            'status' => SpecialCoverageRequest::STATUS_PENDING,
        ]);

        return [$admin, $request];
    }
}
