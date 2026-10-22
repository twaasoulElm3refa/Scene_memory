<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityResponseHeaders;
use App\Models\LicenceType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WithdrawalReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionsAndReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('fixture');
        $this->withToken($token->plainTextToken)->postJson('/api/v1/users/logout')->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_expired_reset_is_rejected_and_valid_reset_revokes_tokens_and_is_single_use(): void
    {
        $user = User::factory()->create();
        $user->createToken('fixture');
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make('123456'), 'created_at' => now()->subHours(2)]);
        $payload = ['email' => $user->email, 'otp' => '123456', 'password' => 'Changed12345', 'password_confirmation' => 'Changed12345'];
        $this->postJson('/api/v1/users/reset-password', $payload)->assertStatus(400);
        $this->assertCount(1, $user->tokens()->get());
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()]);
        $this->postJson('/api/v1/users/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->assertCount(0, $user->tokens()->get());
        $this->postJson('/api/v1/users/reset-password', $payload)->assertStatus(400);
    }

    public function test_admin_login_is_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/admin/login', ['email' => 'fixture@example.test', 'password' => 'incorrect'])->assertUnauthorized();
        }
        $this->postJson('/api/v1/admin/login', ['email' => 'fixture@example.test', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_only_the_selected_reservation_is_settled_and_replays_do_not_credit_twice(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $wallet = Wallet::create(['user_id' => $owner->id, 'amount' => 100, 'balance_minor' => 10000, 'pending' => 0, 'currency' => 'USD']);
        $service = app(WithdrawalReservationService::class);
        $first = $service->create($owner, $wallet->id, ['amount' => 10]);
        $second = $service->create($owner, $wallet->id, ['amount' => 20]);
        $service->transition($admin, $first->id, 'completed');
        $this->assertEquals(20, $wallet->fresh()->pending);
        $service->transition($owner, $second->id, 'rejected');
        $service->transition($owner, $second->id, 'rejected');
        $this->assertSame(9000, $wallet->fresh()->balance_minor);
        $this->assertEquals(90, $wallet->fresh()->amount);
        $this->assertEquals(0, $wallet->fresh()->pending);
        $this->assertSame(4, DB::table('wallet_reservation_movements')->count());
        $this->assertDatabaseCount('withdrawals', 2);
    }

    public function test_overdraft_invalid_precision_and_legacy_discrepancies_fail_without_changes(): void
    {
        $owner = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $owner->id, 'amount' => 10, 'balance_minor' => 1000, 'pending' => 0, 'currency' => 'USD']);
        Sanctum::actingAs($owner);
        foreach ([['amount' => 20], ['amount' => -1], ['amount' => 1, 'fee' => 0.001], ['amount' => 1, 'currency' => 'EUR']] as $data) {
            $this->postJson('/api/v1/withdraw/requestWithdrawals/'.$wallet->id, $data)->assertUnprocessable();
        }
        $wallet->update(['balance_minor' => 2000]);
        $this->postJson('/api/v1/withdraw/requestWithdrawals/'.$wallet->id, ['amount' => 1])->assertUnprocessable();
        $this->assertSame(2000, $wallet->fresh()->balance_minor);
        $this->assertEquals(10, $wallet->fresh()->amount);
        $this->assertDatabaseCount('withdrawals', 0);
    }

    public function test_paid_plan_cannot_be_granted_by_browser_and_free_reward_is_not_repeatable(): void
    {
        Mail::fake();
        $paid = LicenceType::create(['name' => 'premium', 'price' => 50]);
        $free = LicenceType::create(['name' => 'basic', 'price' => 0]);
        $user = User::factory()->create(['points' => 0]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/subscribe/'.$paid->id)->assertUnprocessable();
        $this->postJson('/api/v1/subscribe/'.$free->id)->assertOk();
        $points = $user->fresh()->points;
        $this->postJson('/api/v1/subscribe/'.$free->id)->assertOk();
        $this->assertSame($points, $user->fresh()->points);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_admin_update_transitions_reservations_through_processing_and_completion(): void
    {
        $owner = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $owner->id, 'amount' => 100, 'balance_minor' => 10000, 'pending' => 0, 'currency' => 'USD']);
        $withdrawal = app(WithdrawalReservationService::class)->create($owner, $wallet->id, ['amount' => 10]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/v1/withdraw/update/'.$withdrawal->id, ['status' => 'processing', 'admin_note' => 'Fixture review'])->assertOk();
        $this->assertEquals(10, $wallet->fresh()->pending);
        $this->postJson('/api/v1/withdraw/approve/'.$withdrawal->id)->assertOk();
        $this->postJson('/api/v1/withdraw/approve/'.$withdrawal->id)->assertOk();
        $this->postJson('/api/v1/withdraw/reject/'.$withdrawal->id)->assertUnprocessable();
        $this->assertSame(9000, $wallet->fresh()->balance_minor);
        $this->assertEquals(0, $wallet->fresh()->pending);
        $this->assertDatabaseHas('withdrawals', ['id' => $withdrawal->id, 'status' => 'completed', 'admin_note' => 'Fixture review']);
    }

    public function test_headers_and_production_error_responses_do_not_expose_internal_details(): void
    {
        config(['app.debug' => false]);
        $request = Request::create('/api/security-fixture', 'GET');
        $response = app(SecurityResponseHeaders::class)->handle($request, fn () => response()->json(['message' => 'fixture internal database details'], 500));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertStringNotContainsString('internal database', $response->getContent());
    }
}
