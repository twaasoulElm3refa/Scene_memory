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

    private function exchange(string $code, array $payload = [])
    {
        return $this->postJson('/api/v1/users/facebook-exchange', ['code' => $code] + $payload);
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

    public function test_new_facebook_account_signs_in_immediately_without_profile_or_email_code(): void
    {
        $code = $this->deliver();
        $this->exchange($code, ['role' => 'admin', 'email' => 'attacker@example.test'])
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('role', 'user')
            ->assertJsonPath('user.email', 'facebook@example.test')->assertJsonPath('is_profile_complete', false);
        $user = User::sole();
        $this->assertNull($user->password);
        $this->assertSame('123456789', $user->facebook_id);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->country);
        $this->assertNull($user->date_of_birth);
        $this->assertTrue($user->is_active);
        $this->assertSame('https://platform-lookaside.fbsbx.com/fixture', $user->facebook_avatar_url);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('registration_otps', 0);
        Mail::assertNotSent(RegisterOtpMail::class);
        Mail::assertNothingOutgoing();
    }

    public function test_facebook_verified_flag_does_not_falsely_mark_email_verified_or_block_sign_in(): void
    {
        $code = $this->deliver(['verified' => true]);
        $this->exchange($code)->assertOk();
        $this->assertNull(User::sole()->email_verified_at);
        Mail::assertNotSent(RegisterOtpMail::class);
    }

    public function test_repeated_registration_and_sign_in_reuse_the_same_provider_account(): void
    {
        $code = $this->deliver();
        $first = $this->exchange($code)->assertOk();
        Socialite::forgetDrivers();
        $code = $this->deliver(['email' => null, 'name' => 'Changed Facebook Name']);
        $second = $this->exchange($code)->assertOk();
        $this->assertSame($first->json('user.id'), $second->json('user.id'));
        $this->assertSame('Facebook User', $second->json('user.name'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_log_mailer_cannot_break_facebook_registration_or_login(): void
    {
        $code = $this->deliver();
        config()->set('mail.default', 'log');
        config()->set('app.debug', false);
        $this->exchange($code)->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Mail::assertNotSent(RegisterOtpMail::class);
    }

    public function test_missing_facebook_email_creates_distinct_email_less_accounts_without_fabricated_values(): void
    {
        $code = $this->deliver(['email' => null]);
        $first = $this->exchange($code)->assertOk()->assertJsonPath('user.email', null);
        Socialite::forgetDrivers();
        $code = $this->deliver(['id' => '987654321', 'email' => null]);
        $second = $this->exchange($code)->assertOk()->assertJsonPath('user.email', null);
        $this->assertNotSame($first->json('user.id'), $second->json('user.id'));
        $this->assertDatabaseCount('users', 2);
        foreach (User::all() as $user) {
            $this->assertNull($user->email);
            $this->assertNull($user->country);
            $this->assertNull($user->date_of_birth);
        }
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

    public function test_client_supplied_email_cannot_choose_or_claim_an_existing_account(): void
    {
        $code = $this->deliver(['email' => null]);
        $user = User::factory()->create(['email' => 'facebook@example.test']);
        $this->exchange($code, ['email' => $user->email, 'role' => 'admin'])->assertOk()->assertJsonPath('user.email', null);
        $this->assertNull($user->fresh()->facebook_id);
        $this->assertDatabaseCount('users', 2);
        Mail::assertNotSent(RegisterOtpMail::class);
    }

    public function test_concurrent_email_claim_between_callback_and_exchange_cannot_link_or_duplicate(): void
    {
        $code = $this->deliver();
        User::factory()->create(['email' => 'facebook@example.test']);
        $this->exchange($code)->assertStatus(409)->assertJsonPath('error_code', 'linking_required');
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

    public function test_client_profile_and_verification_fields_are_not_needed_or_trusted(): void
    {
        $code = $this->deliver();
        $this->exchange($code, ['email' => 'invalid', 'country' => 'fabricated', 'date_of_birth' => '2999-01-01', 'otp' => 'invalid', 'facebook_id' => '999'])
            ->assertOk()->assertJsonPath('user.email', 'facebook@example.test');
        $user = User::sole();
        $this->assertSame('123456789', $user->facebook_id);
        $this->assertNull($user->country);
        $this->assertNull($user->date_of_birth);
        Mail::assertNotSent(RegisterOtpMail::class);
    }

    public function test_unverified_facebook_email_is_not_an_anonymous_registration_or_recovery_credential(): void
    {
        $user = $this->linked(['email_verified_at' => null]);
        $before = $user->fresh()->getAttributes();
        foreach (['register', 'register/resend-otp', 'register/verify-otp', 'login', 'forgot-password', 'reset-password'] as $index => $path) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.'.($index + 10)]);
            $this->postJson('/api/v1/users/'.$path, ['email' => $user->email, 'otp' => '123456', 'password' => 'LocalPassword123!'])
                ->assertForbidden();
        }
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('registration_otps', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Mail::assertNothingSent();
        $code = $this->deliver();
        $this->exchange($code)->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_facebook_authenticated_owner_can_verify_contact_email_later_with_existing_verification_flow(): void
    {
        $user = $this->linked(['email_verified_at' => null]);
        $token = $user->createToken('facebook-auth-token')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/users/register/resend-otp', ['email' => $user->email])->assertOk();
        $otp = Mail::sent(RegisterOtpMail::class)->last()->otp;
        $this->postJson('/api/v1/users/register/verify-otp', ['email' => $user->email, 'otp' => $otp])->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue($user->fresh()->is_active);
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

    public function test_nullable_email_migration_preserves_existing_users_and_refuses_destructive_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_22_000003_allow_email_less_social_accounts.php');
        $migration->down();
        $user = User::factory()->create(['google_id' => 'existing-google-sub']);
        $before = $user->fresh()->getAttributes();
        $migration->up();
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->linked(['email' => null]);
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
