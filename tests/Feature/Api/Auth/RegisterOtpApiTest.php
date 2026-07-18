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
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissing(['password' => $this->password(), 'otp' => true]);

        $user = User::where('email', 'new-user@example.com')->firstOrFail();

        $this->assertFalse((bool) $user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('registration_otps', 1);
        $this->assertNotSame($this->latestOtp(), RegistrationOtp::first()->otp_hash);

        Mail::assertSent(RegisterOtpMail::class, function (RegisterOtpMail $mail) {
            return preg_match('/^\d{6}$/', $mail->otp) === 1;
        });
    }

    public function test_correct_otp_activates_user_sets_email_verified_at_and_returns_login_token(): void
    {
        [$user, $otp] = $this->registerAndReadOtp();

        $response = $this->postJson('/api/v1/users/register/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Email verified successfully.')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token']]);

        $user->refresh();

        $this->assertTrue((bool) $user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull(RegistrationOtp::first()->verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
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
        $user = User::factory()->create([
            'email' => 'blocked@gmail.com',
            'is_active' => false,
            'email_verified_at' => null,
            'password' => Hash::make($this->password()),
        ]);

        $this->postJson('/api/v1/users/login', [
            'email' => $user->email,
            'password' => $this->password(),
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Please verify your email first.');
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
            User::where('email', 'new-user@example.com')->firstOrFail(),
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
            'email' => 'new-user@example.com',
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
