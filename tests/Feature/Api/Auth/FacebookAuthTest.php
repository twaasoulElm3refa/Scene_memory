<?php

namespace Tests\Feature\Api\Auth;

use App\Mail\RegisterOtpMail;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Tests\TestCase;

class FacebookAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        config()->set('app.frontend_url', 'https://scemory.com');
        config()->set('services.facebook.client_id', 'fixture-facebook-app');
        config()->set('services.facebook.client_secret', 'fixture-facebook-secret');
        config()->set('services.facebook.redirect', 'https://scemory.com/api/v1/users/facebook-callback');
        Mail::fake();
    }

    private function start(string $lang = 'en'): array
    {
        $response = $this->getJson('/api/v1/users/facebook-login?'.http_build_query(['lang' => $lang]));
        $response->assertOk();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        parse_str(parse_url($response->json('url'), PHP_URL_QUERY), $query);
        Socialite::forgetDrivers();

        return $query;
    }

    private function provider(array $identity = [], int $tokenStatus = 200, int $identityStatus = 200): void
    {
        $responses = new MockHandler([
            new Response($tokenStatus, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'server-only-facebook-token', 'token_type' => 'Bearer'])),
            new Response($identityStatus, ['Content-Type' => 'application/json'], json_encode($identity + [
                'id' => '123456789', 'email' => 'facebook@example.test', 'name' => 'Facebook User',
                'picture' => ['data' => ['url' => 'https://platform-lookaside.fbsbx.com/fixture']],
                'verified' => true,
            ])),
        ]);
        Socialite::extend('facebook', fn ($app) => (new FacebookProvider(
            $app['request'], config('services.facebook.client_id'), config('services.facebook.client_secret'), config('services.facebook.redirect')
        ))->setHttpClient(new Client(['handler' => HandlerStack::create($responses)])));
    }

    private function deliver(array $identity = [], string $lang = 'en'): string
    {
        $tokensBefore = PersonalAccessToken::count();
        $query = $this->start($lang);
        $this->provider($identity);
        $response = $this->get('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'facebook-authorization-code']));
        $response->assertRedirectContains("https://scemory.com/{$lang}/auth/facebook-callback?code=");
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $result);
        $this->assertArrayNotHasKey('token', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertDatabaseCount('personal_access_tokens', $tokensBefore);

        return $result['code'];
    }

    private function profile(): array
    {
        return ['email' => 'facebook@example.test', 'country' => 'Egypt', 'date_of_birth' => '2000-01-01'];
    }

    private function exchange(string $code, array $payload = [])
    {
        return $this->postJson('/api/v1/users/facebook-exchange', ['code' => $code] + $payload);
    }

    private function otp(): string
    {
        return Mail::sent(RegisterOtpMail::class)->last()->otp;
    }

    private function linked(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['facebook_id' => '123456789', 'email' => 'facebook@example.test', 'is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_authorization_uses_configured_client_exact_redirect_email_scope_and_session_state(): void
    {
        $query = $this->start();
        $this->assertNotEmpty($query['state']);
        $this->assertSame('fixture-facebook-app', $query['client_id']);
        $this->assertSame('https://scemory.com/api/v1/users/facebook-callback', $query['redirect_uri']);
        $this->assertSame('email', $query['scope']);
        $this->assertArrayNotHasKey('client_secret', $query);
    }

    public function test_new_registration_requires_profile_and_email_proof_before_creating_a_passwordless_account(): void
    {
        $code = $this->deliver();
        $this->exchange($code)->assertStatus(202)->assertJsonPath('status', 'profile_required')->assertJsonPath('email', 'facebook@example.test');
        $this->exchange($code, $this->profile() + ['role' => 'admin'])
            ->assertStatus(202)->assertJsonPath('status', 'otp_required');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->exchange($code, ['otp' => $this->otp(), 'email' => 'attacker@example.test', 'role' => 'admin'])
            ->assertOk()->assertJsonPath('role', 'user')->assertJsonPath('user.email', 'facebook@example.test');
        $user = User::sole();
        $this->assertNull($user->password);
        $this->assertSame('123456789', $user->facebook_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Egypt', $user->country);
        $this->assertSame('https://platform-lookaside.fbsbx.com/fixture', $user->facebook_avatar_url);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertStringNotContainsString($this->otp(), json_encode(Cache::get('facebook-delivery:'.hash('sha256', $code))));
    }

    public function test_facebook_verified_flag_is_not_treated_as_email_verification(): void
    {
        $code = $this->deliver(['verified' => true]);
        $this->exchange($code, $this->profile())->assertStatus(202)->assertJsonPath('status', 'otp_required');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_repeated_registration_and_sign_in_reuse_the_same_provider_account(): void
    {
        $code = $this->deliver();
        $this->exchange($code, $this->profile())->assertStatus(202);
        $first = $this->exchange($code, ['otp' => $this->otp()])->assertOk();
        Socialite::forgetDrivers();
        $code = $this->deliver(['email' => null, 'name' => 'Changed Facebook Name']);
        $second = $this->exchange($code)->assertOk();
        $this->assertSame($first->json('user.id'), $second->json('user.id'));
        $this->assertSame('Facebook User', $second->json('user.name'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unsafe_email_delivery_configuration_cannot_create_or_verify_an_account(): void
    {
        $code = $this->deliver();
        config()->set('mail.default', 'log');
        $this->exchange($code, $this->profile())->assertStatus(503);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Mail::assertNotSent(RegisterOtpMail::class);
    }

    public function test_missing_facebook_email_can_be_entered_and_must_be_verified(): void
    {
        $code = $this->deliver(['email' => null]);
        $this->exchange($code)->assertStatus(202)->assertJsonPath('email', null);
        $this->exchange($code, $this->profile())->assertStatus(202);
        $this->exchange($code, ['otp' => $this->otp()])->assertOk();
    }

    public function test_existing_linked_user_is_found_by_provider_id_even_without_email_and_preserves_all_profile_data(): void
    {
        $user = $this->linked(['role' => 'creator', 'country' => 'Original', 'name' => 'Original Name']);
        $user->wallet()->create(['amount' => 147, 'balance_minor' => 14700]);
        $before = $user->getAttributes();
        $code = $this->deliver(['email' => null, 'name' => 'Changed Name']);
        $response = $this->exchange($code)->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('role', 'creator');
        foreach (['name', 'email', 'password', 'role', 'country'] as $field) {
            $this->assertSame($before[$field], $user->fresh()->getRawOriginal($field));
        }
        $this->assertSame(14700, $user->fresh()->wallet->balance_minor);
        $this->assertDatabaseCount('users', 1);
        Mail::assertNotSent(RegisterOtpMail::class);
        $token = $response->json('token');
        $this->withToken($token)->postJson('/api/v1/users/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/users/profile')->assertUnauthorized();
    }

    public function test_matching_password_or_google_email_is_never_automatically_linked(): void
    {
        foreach ([null, 'existing-google-subject'] as $googleId) {
            $user = User::factory()->create(['email' => 'facebook@example.test', 'google_id' => $googleId]);
            $before = $user->fresh()->getAttributes();
            $query = $this->start();
            $this->provider();
            $this->getJson('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
                ->assertStatus(409)->assertJsonPath('error_code', 'linking_required');
            $this->assertSame($before, $user->fresh()->getAttributes());
            $this->assertDatabaseCount('personal_access_tokens', 0);
            $user->delete();
            Socialite::forgetDrivers();
        }
    }

    public function test_email_entered_in_onboarding_cannot_claim_an_existing_account(): void
    {
        $code = $this->deliver(['email' => null]);
        $user = User::factory()->create(['email' => 'facebook@example.test']);
        $this->exchange($code, $this->profile())->assertStatus(409)->assertJsonPath('error_code', 'linking_required');
        $this->assertNull($user->fresh()->facebook_id);
        Mail::assertNotSent(RegisterOtpMail::class);
    }

    public function test_duplicate_email_claim_between_email_challenge_and_completion_cannot_link_or_duplicate(): void
    {
        $code = $this->deliver(['email' => null]);
        $this->exchange($code, $this->profile())->assertStatus(202);
        User::factory()->create(['email' => 'facebook@example.test']);
        $this->exchange($code, ['otp' => $this->otp()])->assertStatus(409);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_missing_invalid_expired_and_replayed_states_are_rejected(): void
    {
        $this->getJson('/api/v1/users/facebook-callback?code=fixture')->assertStatus(400)->assertJsonPath('error_code', 'invalid_state');
        $query = $this->start();
        $this->getJson('/api/v1/users/facebook-callback?state=wrong&code=fixture')->assertStatus(400);
        $this->getJson('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertStatus(400);
        $query = $this->start();
        $this->travel(11)->minutes();
        $this->getJson('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertStatus(400);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_cancellation_and_callback_errors_preserve_language_without_exposing_provider_errors(): void
    {
        $query = $this->start('ar');
        $this->get('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'error' => 'access_denied', 'error_description' => 'secret details']))
            ->assertRedirect('https://scemory.com/ar/auth/facebook-callback?error=cancelled');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_expired_and_unauthorized_provider_responses_do_not_authenticate(): void
    {
        foreach ([[400, 200], [401, 200], [200, 401]] as [$tokenStatus, $identityStatus]) {
            $query = $this->start();
            $this->provider(tokenStatus: $tokenStatus, identityStatus: $identityStatus);
            $this->getJson('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'invalid-or-expired']))
                ->assertStatus(400)->assertJsonPath('error_code', 'callback_failed');
            Socialite::forgetDrivers();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_malformed_provider_identifier_is_rejected(): void
    {
        $query = $this->start();
        $this->provider(['id' => 'frontend-supplied-id']);
        $this->getJson('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertStatus(400)->assertJsonPath('error_code', 'identity_invalid');
    }

    public function test_delivery_is_session_bound_single_use_and_expires(): void
    {
        $this->linked();
        $code = $this->deliver();
        $original = $this->app['session']->getId();
        $this->withCookie(config('session.cookie'), str_repeat('b', 40));
        $this->exchange($code)->assertStatus(422)->assertJsonPath('error_code', 'invalid_state');
        $this->withCookie(config('session.cookie'), $original);
        $this->exchange($code)->assertOk();
        $this->exchange($code)->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
        Socialite::forgetDrivers();
        // A new provider delivery does not issue a token before its exchange.
        $query = $this->start();
        $this->provider();
        $response = $this->get('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $result);
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->travel(16)->minutes();
        $this->exchange($result['code'])->assertStatus(422);
    }

    public function test_real_csrf_middleware_rejects_missing_token_and_accepts_matching_token(): void
    {
        $this->linked();
        $code = $this->deliver();
        $csrf = $this->app['session']->token();
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->exchange($code)->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', $csrf);
        $this->exchange($code)->assertOk();
    }

    public function test_suspension_is_enforced_at_callback_and_at_exchange(): void
    {
        $user = $this->linked();
        $code = $this->deliver();
        $user->update(['is_active' => false]);
        $this->exchange($code)->assertForbidden()->assertJsonPath('error_code', 'disabled');
        Socialite::forgetDrivers();
        $query = $this->start();
        $this->provider();
        $this->getJson('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertForbidden();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_required_profile_fields_are_validated_before_email_is_sent(): void
    {
        $code = $this->deliver();
        $this->exchange($code, ['email' => 'invalid', 'country' => 'Egypt', 'date_of_birth' => '2000-01-01'])->assertUnprocessable();
        $this->exchange($code, ['email' => 'new@example.test', 'country' => 'Egypt', 'date_of_birth' => '2999-01-01'])->assertUnprocessable();
        Mail::assertNotSent(RegisterOtpMail::class);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_wrong_expired_and_excessive_email_codes_cannot_create_accounts(): void
    {
        $code = $this->deliver();
        $this->exchange($code, $this->profile())->assertStatus(202);
        $correct = $this->otp();
        $wrong = $correct === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) {
            $this->exchange($code, ['otp' => $wrong])->assertStatus(422)->assertJsonPath('error_code', 'otp_invalid');
        }
        $this->exchange($code, ['otp' => $correct])->assertStatus(429)->assertJsonPath('error_code', 'otp_attempts');
        $this->travel(11)->minutes();
        $this->exchange($code, ['otp' => $correct])->assertStatus(422)->assertJsonPath('error_code', 'otp_expired');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_resends_enforce_cooldown_and_invalidate_old_code(): void
    {
        $code = $this->deliver();
        $this->exchange($code, $this->profile())->assertStatus(202);
        $old = $this->otp();
        $this->exchange($code, ['resend' => true])->assertStatus(429)->assertJsonPath('error_code', 'otp_cooldown');
        $this->travel(61)->seconds();
        $this->exchange($code, ['resend' => true, 'email' => 'changed@example.test'])->assertStatus(202);
        $challenge = Cache::get('facebook-delivery:'.hash('sha256', $code));
        $this->assertSame('facebook@example.test', $challenge['verification']['profile']['email']);
        $this->assertNotSame($old, $challenge['verification']['hash']);
        $this->exchange($code, ['otp' => $this->otp()])->assertOk();
    }

    public function test_existing_google_oauth_state_and_pkce_are_not_changed_by_facebook_flow(): void
    {
        $this->withSession(['state' => 'existing-google-state', 'code_verifier' => 'existing-google-verifier', 'google_oauth' => ['lang' => 'ar']]);
        $query = $this->start();
        $this->assertSame('existing-google-state', session('state'));
        $this->assertSame('existing-google-verifier', session('code_verifier'));
        $this->provider();
        $this->get('/api/v1/users/facebook-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertRedirectContains('code=');
        $this->assertSame('existing-google-state', session('state'));
        $this->assertSame('existing-google-verifier', session('code_verifier'));
        $this->assertSame(['lang' => 'ar'], session('google_oauth'));
    }

    public function test_configuration_missing_or_unsafe_urls_fail_without_exposing_secrets(): void
    {
        config()->set('services.facebook.client_secret', null);
        $this->getJson('/api/v1/users/facebook-login')->assertStatus(503)->assertJsonPath('error_code', 'unavailable');
        config()->set('services.facebook.client_secret', 'secret-value');
        config()->set('app.env', 'production');
        config()->set('services.facebook.redirect', 'http://scemory.com/api/v1/users/facebook-callback');
        $this->getJson('/api/v1/users/facebook-login')->assertStatus(503)->assertDontSee('secret-value');
        config()->set('services.facebook.redirect', 'https://scemory.com/api/v1/users/facebook-callback');
        $response = $this->getJson('/api/v1/users/facebook-login?return_url=https://attacker.test&lang=../../evil')->assertOk();
        $this->assertStringNotContainsString('attacker.test', $response->json('url'));
    }

    public function test_schema_upgrade_preserves_existing_google_identity_password_role_and_status(): void
    {
        $migration = require database_path('migrations/2026_10_22_000002_add_facebook_identity_to_users.php');
        $migration->down();
        $user = User::factory()->create(['google_id' => 'original-google-id', 'role' => 'creator', 'is_active' => false]);
        $before = $user->getAttributes();
        $migration->up();
        foreach (['google_id', 'name', 'email', 'password', 'role', 'is_active'] as $field) {
            $this->assertEquals($before[$field], $user->fresh()->getRawOriginal($field));
        }
        $this->assertNull($user->fresh()->facebook_id);
    }
}
