<?php

namespace Tests\Feature\Api\Auth;

use App\Mail\RegisterOtpMail;
use App\Models\RegistrationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegisterOtpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }

    public function test_register_creates_inactive_user_sends_otp_and_does_not_return_token(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/users/register', $this->payload());

        $response->assertStatus(202)
            ->assertJsonPath('status', 'otp_required')
            ->assertJsonPath('data.expires_in', 600)
            ->assertJsonPath('data.resend_after', 60)
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissing(['password' => $this->password(), 'otp' => true]);

        $user = User::where('email', 'new-user@gmail.com')->firstOrFail();

        $this->assertFalse((bool) $user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('registration_otps', 1);
        $this->assertNotSame($this->latestOtp(), RegistrationOtp::first()->otp_hash);

        Mail::assertSent(RegisterOtpMail::class, function (RegisterOtpMail $mail) {
            return preg_match('/^\d{6}$/', $mail->otp) === 1;
        });
    }

    public function test_correct_otp_verifies_email_without_returning_login_token(): void
    {
        [$user, $otp] = $this->registerAndReadOtp();

        $response = $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'email_verified')
            ->assertJsonPath('message', 'Email verified successfully. Please login.')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('access_token');

        $user->refresh();

        $this->assertTrue((bool) $user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->last_login_at);
        $this->assertNotNull(RegistrationOtp::first()->verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_otp_returns_422_and_increments_attempts(): void
    {
        [$user] = $this->registerAndReadOtp();

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid verification code.');

        $this->assertSame(1, RegistrationOtp::first()->attempts);
    }

    public function test_expired_otp_is_rejected(): void
    {
        [$user, $otp] = $this->registerAndReadOtp();

        $this->travel(11)->minutes();

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Verification code has expired.');
    }

    public function test_otp_cannot_be_used_twice(): void
    {
        [$user, $otp] = $this->registerAndReadOtp();

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ])->assertOk();

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This account is already verified.');
    }

    public function test_too_many_wrong_attempts_are_rejected(): void
    {
        [$user] = $this->registerAndReadOtp();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/users/register/verify-otp', [
                'email' => $user->email,
                'otp' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => '000000',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Too many verification attempts.');
    }

    public function test_resend_creates_new_otp_invalidates_old_otp_and_respects_cooldown(): void
    {
        [$user, $oldOtp] = $this->registerAndReadOtp();

        $this->postJson('/api/v1/users/register/resend-otp', [
            'email' => $user->email,
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Please wait before requesting another code.');

        $this->travel(61)->seconds();
        Mail::fake();

        $this->postJson('/api/v1/users/register/resend-otp', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath('status', 'otp_resent')
            ->assertJsonPath('data.resend_after', 60);

        $newOtp = $this->latestOtp();

        $this->assertNotSame($oldOtp, $newOtp);
        $this->assertNotNull(RegistrationOtp::oldest('id')->first()->verified_at);

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $oldOtp,
        ])->assertStatus(422);

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $newOtp,
        ])->assertOk();
    }

    public function test_inactive_duplicate_email_updates_existing_user_without_creating_duplicate(): void
    {
        Mail::fake();

        $first = $this->payload(['email' => 'retry@example.com', 'name' => 'Retry User']);
        $second = $this->payload([
            'email' => 'retry@example.com',
            'name' => 'Retry User Updated',
            'country' => 'Egypt',
        ]);

        $this->postJson('/api/v1/users/register', $first)->assertStatus(202);
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/users/register', $second)->assertStatus(202);

        $this->assertSame(1, User::where('email', 'retry@example.com')->count());
        $this->assertSame('Retry User Updated', User::where('email', 'retry@example.com')->value('name'));
    }

    public function test_verified_email_cannot_request_new_register_otp(): void
    {
        User::factory()->create([
            'email' => 'verified@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make($this->password()),
        ]);

        $this->postJson('/api/v1/users/register', $this->payload([
            'email' => 'verified@example.com',
        ]))->assertStatus(422);
    }

    public function test_login_blocks_unactivated_user(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'blocked@gmail.com',
            'is_active' => true,
            'email_verified_at' => null,
            'password' => Hash::make($this->password()),
        ]);

        $this->postJson('/api/v1/users/login', [
            'email' => $user->email,
            'password' => $this->password(),
        ])->assertStatus(403)
            ->assertJsonPath('status', 'otp_required')
            ->assertJsonPath('message', 'Please verify your email first.')
            ->assertJsonMissingPath('data.token');

        Mail::assertSent(RegisterOtpMail::class);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('registration_otps', 1);
    }

    public function test_login_with_wrong_password_does_not_send_otp(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'wrong-password@gmail.com',
            'is_active' => true,
            'email_verified_at' => null,
            'password' => Hash::make($this->password()),
        ]);

        $this->postJson('/api/v1/users/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!123',
        ])->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('registration_otps', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_unknown_email_returns_invalid_credentials_without_otp(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/users/login', [
            'email' => 'unknown-user@gmail.com',
            'password' => $this->password(),
        ])->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('registration_otps', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_after_otp_verification_user_must_login_again_to_receive_token(): void
    {
        [$user, $otp] = $this->registerAndReadOtp();

        $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ])->assertOk()
            ->assertJsonPath('status', 'email_verified')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/v1/users/login', [
            'email' => $user->email,
            'password' => $this->password(),
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_user_with_null_email_verified_at_never_receives_token_from_normal_login(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'legacy-unverified@gmail.com',
            'is_active' => true,
            'email_verified_at' => null,
            'password' => Hash::make($this->password()),
        ]);

        $this->postJson('/api/v1/users/login', [
            'email' => $user->email,
            'password' => $this->password(),
        ])->assertStatus(403)
            ->assertJsonPath('status', 'otp_required')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_resend_works_for_otp_started_from_login(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'login-resend@gmail.com',
            'is_active' => true,
            'email_verified_at' => null,
            'password' => Hash::make($this->password()),
        ]);

        $this->postJson('/api/v1/users/login', [
            'email' => $user->email,
            'password' => $this->password(),
        ])->assertStatus(403)
            ->assertJsonPath('status', 'otp_required');

        $oldOtp = $this->latestOtp();
        $this->travel(61)->seconds();
        Mail::fake();

        $this->postJson('/api/v1/users/register/resend-otp', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath('status', 'otp_resent');

        $newOtp = $this->latestOtp();

        $this->assertNotSame($oldOtp, $newOtp);
    }

    public function test_register_otp_routes_have_expected_throttle_middleware(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->uri());

        $this->assertContains('throttle:4,1', $routes['api/v1/users/register']->gatherMiddleware());
        $this->assertContains('throttle:6,1', $routes['api/v1/users/register/verify-otp']->gatherMiddleware());
        $this->assertContains('throttle:3,1', $routes['api/v1/users/register/resend-otp']->gatherMiddleware());
    }

    private function registerAndReadOtp(): array
    {
        Mail::fake();

        $this->postJson('/api/v1/users/register', $this->payload())->assertStatus(202);

        return [
            User::where('email', 'new-user@gmail.com')->firstOrFail(),
            $this->latestOtp(),
        ];
    }

    private function latestOtp(): string
    {
        $otp = null;

        Mail::assertSent(RegisterOtpMail::class, function (RegisterOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $this->assertIsString($otp);

        return $otp;
    }

    private function payload(array $overrides = []): array
    {
        $password = $this->password();

        return array_merge([
            'name' => 'New User',
            'email' => 'new-user@gmail.com',
            'country' => 'Egypt',
            'phone' => '01000000000',
            'position' => 'Photographer',
            'date_of_birth' => '1990-01-01',
            'password' => $password,
            'password_confirmation' => $password,
        ], $overrides);
    }

    private function password(): string
    {
        return 'Scemory!'.str_repeat('A9', 8).'#';
    }
}
