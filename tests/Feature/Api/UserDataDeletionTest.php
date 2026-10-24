<?php

namespace Tests\Feature\Api;

use App\Mail\DataDeletionConfirmationMail;
use App\Models\UserDataDeletionRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdraw;
use App\Services\GoogleAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserDataDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_deletion_endpoints(): void
    {
        $this->postJson('/api/v1/users/data-deletion-request', [
            'confirmation_phrase' => 'DELETE',
        ])->assertStatus(401);

        $this->postJson('/api/v1/users/data-deletion-confirm', [
            'confirmation_phrase' => 'DELETE',
            'password' => 'secret123',
        ])->assertStatus(401);

        $this->getJson('/api/v1/users/data-deletion-status')
            ->assertStatus(401);

        $this->postJson('/api/v1/users/data-deletion-cancel')
            ->assertStatus(401);
    }

    public function test_user_must_type_delete_phrase_to_submit(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'NO',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation_phrase']);
    }

    public function test_user_with_password_submits_request_requiring_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
                'reason' => 'I no longer need this service',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', UserDataDeletionRequest::STATUS_PENDING_CONFIRMATION)
            ->assertJsonPath('data.requires_code', false);

        $this->assertDatabaseHas('user_data_deletion_requests', [
            'user_id' => $user->id,
            'status' => UserDataDeletionRequest::STATUS_PENDING_CONFIRMATION,
            'requires_code' => 0,
        ]);
    }

    public function test_user_with_social_account_submits_request_requiring_code_and_mail_is_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'social@example.com',
            'password' => Hash::make('password'),
            'google_id' => '1234567890',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.requires_code', true);

        Mail::assertSent(DataDeletionConfirmationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_submitting_duplicate_active_request_returns_existing_request(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->json('data.reference');

        $second = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->json('data.reference');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('user_data_deletion_requests', 1);
    }

    public function test_confirm_with_wrong_password_fails_and_preserves_user(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->assertStatus(200);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-confirm', [
                'confirmation_phrase' => 'DELETE',
                'password' => 'WrongPassword!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $user->refresh();
        $this->assertTrue((bool) $user->is_active);
        $this->assertNull($user->deleted_at);
    }

    public function test_confirm_with_correct_password_deletes_and_anonymizes_account_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'phone' => '+1234567890',
            'password' => Hash::make('CorrectPassword123!'),
            'google_id' => 'google-12345',
            'facebook_id' => 'fb-12345',
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Submit
        $this->withToken($token)
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->assertStatus(200);

        // Confirm
        $confirmResponse = $this->withToken($token)
            ->postJson('/api/v1/users/data-deletion-confirm', [
                'confirmation_phrase' => 'DELETE',
                'password' => 'CorrectPassword123!',
            ]);

        $confirmResponse->assertStatus(200)
            ->assertJsonPath('data.status', UserDataDeletionRequest::STATUS_COMPLETED);

        $user->refresh();
        $this->assertFalse((bool) $user->is_active);
        $this->assertNotNull($user->deleted_at);
        $this->assertStringStartsWith('Deleted User #', $user->name);
        $this->assertStringStartsWith('deleted-', $user->email);
        $this->assertNull($user->phone);
        $this->assertNull($user->google_id);
        $this->assertNull($user->facebook_id);
        $this->assertFalse(password_verify('CorrectPassword123!', $user->password));

        // Tokens revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_confirm_social_account_with_code_successfully_deletes(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'social2@example.com',
            'password' => Hash::make('password'),
            'google_id' => 'google-sub-777',
        ]);

        $token = $user->createToken('social-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->assertStatus(200);

        $request = UserDataDeletionRequest::where('user_id', $user->id)->first();
        $this->assertNotNull($request->confirmation_code_hash);

        // Simulate code entry
        // Set fixed code
        $request->update([
            'confirmation_code_hash' => Hash::make('654321'),
            'confirmation_code_expires_at' => now()->addMinutes(15),
        ]);

        // Wrong code fails
        $this->withToken($token)
            ->postJson('/api/v1/users/data-deletion-confirm', [
                'confirmation_phrase' => 'DELETE',
                'code' => '111111',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Correct code succeeds
        $this->withToken($token)
            ->postJson('/api/v1/users/data-deletion-confirm', [
                'confirmation_phrase' => 'DELETE',
                'code' => '654321',
            ])->assertStatus(200)
            ->assertJsonPath('data.status', UserDataDeletionRequest::STATUS_COMPLETED);

        $user->refresh();
        $this->assertFalse((bool) $user->is_active);
        $this->assertNotNull($user->deleted_at);
    }

    public function test_user_with_active_wallet_balance_moves_to_under_review(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'amount' => 150.00,
            'balance_minor' => 15000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->assertStatus(200);

        $confirm = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-confirm', [
                'confirmation_phrase' => 'DELETE',
                'password' => 'CorrectPassword123!',
            ]);

        $confirm->assertStatus(200)
            ->assertJsonPath('data.status', UserDataDeletionRequest::STATUS_UNDER_REVIEW)
            ->assertJsonPath('data.financial_obligations_detected', true);

        $user->refresh();
        // User not deleted yet because of pending financial obligations
        $this->assertTrue((bool) $user->is_active);
        $this->assertNull($user->deleted_at);
    }

    public function test_user_with_pending_withdrawal_moves_to_under_review(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        Withdraw::create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->assertStatus(200);

        $confirm = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-confirm', [
                'confirmation_phrase' => 'DELETE',
                'password' => 'CorrectPassword123!',
            ]);

        $confirm->assertStatus(200)
            ->assertJsonPath('data.status', UserDataDeletionRequest::STATUS_UNDER_REVIEW);

        $user->refresh();
        $this->assertTrue((bool) $user->is_active);
    }

    public function test_user_can_cancel_pending_request(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ])->assertStatus(200);

        $cancelResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-cancel');

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', UserDataDeletionRequest::STATUS_CANCELLED);

        $this->assertDatabaseHas('user_data_deletion_requests', [
            'user_id' => $user->id,
            'status' => UserDataDeletionRequest::STATUS_CANCELLED,
        ]);
    }

    public function test_public_status_returns_sanitized_status_without_pii(): void
    {
        $user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $submit = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/users/data-deletion-request', [
                'confirmation_phrase' => 'DELETE',
            ]);

        $ref = $submit->json('data.reference');

        $status = $this->getJson('/api/v1/data-deletion-status/' . $ref);

        $status->assertStatus(200)
            ->assertJsonPath('data.reference', $ref)
            ->assertJsonPath('data.status', UserDataDeletionRequest::STATUS_PENDING_CONFIRMATION)
            ->assertJsonMissing(['email' => 'john.doe@example.com'])
            ->assertJsonMissing(['password'])
            ->assertJsonMissing(['user_id']);
    }

    public function test_admin_can_list_and_process_under_review_deletion_request(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $request = UserDataDeletionRequest::create([
            'reference' => 'DEL-REVIEW-001',
            'user_id' => $user->id,
            'user_identifier' => hash('sha256', (string) $user->id),
            'status' => UserDataDeletionRequest::STATUS_UNDER_REVIEW,
            'financial_obligations_detected' => true,
            'financial_hold_reason' => 'Admin review needed',
        ]);

        // List
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/data-deletion-requests?status=under_review')
            ->assertStatus(200)
            ->assertJsonPath('data.data.0.reference', 'DEL-REVIEW-001');

        // Admin Process
        $process = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/data-deletion-requests/' . $request->id . '/process', [
                'admin_notes' => 'Settled offline with customer',
            ]);

        $process->assertStatus(200);

        $user->refresh();
        $this->assertFalse((bool) $user->is_active);
        $this->assertNotNull($user->deleted_at);

        $request->refresh();
        $this->assertSame(UserDataDeletionRequest::STATUS_COMPLETED, $request->status);
    }
}
