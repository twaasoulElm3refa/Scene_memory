<?php

namespace Tests\Feature\Api\Auth;

use App\Mail\GoogleRecoveryCodeMail;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleWebAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        config()->set('app.frontend_url', 'https://scemory.com');
        config()->set('services.google.client_id', 'fixture-client');
        config()->set('services.google.client_secret', 'fixture-secret');
        config()->set('services.google.redirect', 'https://scemory.com/api/v1/users/google-callback');
        Mail::fake();
    }

    private function start(string $lang = 'en'): array
    {
        $response = $this->getJson('/api/v1/users/google-login?'.http_build_query(['lang' => $lang]));
        $response->assertOk();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        parse_str(parse_url($response->json('url'), PHP_URL_QUERY), $query);
        Socialite::forgetDrivers();

        return $query;
    }

    private function provider(array $identity = [], int $tokenStatus = 200, int $identityStatus = 200): void
    {
        $responses = new MockHandler([
            new Response($tokenStatus, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'server-only-access-token', 'token_type' => 'Bearer'])),
            new Response($identityStatus, ['Content-Type' => 'application/json'], json_encode($identity + [
                'sub' => 'google-sub-123', 'email' => 'google@example.test', 'email_verified' => true,
                'name' => 'Google User', 'picture' => 'https://lh3.googleusercontent.com/fixture',
            ])),
        ]);
        Socialite::extend('google', fn ($app) => (new GoogleProvider(
            $app['request'], config('services.google.client_id'), config('services.google.client_secret'), config('services.google.redirect')
        ))->setHttpClient(new Client(['handler' => HandlerStack::create($responses)])));
    }

    private function completeCallback(array $identity = [], string $lang = 'en'): string
    {
        $query = $this->start($lang);
        $this->provider($identity);
        $response = $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'server-authorization-code']));
        $response->assertRedirectContains("https://scemory.com/{$lang}/auth/google-callback?code=");
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->assertStringNotContainsString('token=', $response->headers->get('Location'));
        $this->assertStringNotContainsString('email=', $response->headers->get('Location'));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $result);

        return $result['code'];
    }

    public function test_web_authorization_uses_state_pkce_and_exact_configured_redirect(): void
    {
        $query = $this->start();
        $this->assertNotEmpty($query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(43, strlen($query['code_challenge']));
        $this->assertSame('fixture-client', $query['client_id']);
        $this->assertSame(config('services.google.redirect'), $query['redirect_uri']);
        $this->assertStringContainsString('openid', $query['scope']);
    }

    public function test_new_user_signs_in_immediately_without_profile_or_password(): void
    {
        $code = $this->completeCallback();
        $this->assertDatabaseCount('users', 0);
        $this->postJson('/api/v1/users/google-exchange', [
            'code' => $code,
            'country' => 'fabricated',
            'date_of_birth' => '2999-01-01',
            'role' => 'admin',
        ])
            ->assertOk()->assertJsonPath('role', 'user')->assertJsonPath('user.email', 'google@example.test');
        $user = User::sole();
        $this->assertNull($user->password);
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/fixture', $user->google_avatar_url);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->country);
        $this->assertNull($user->date_of_birth);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_existing_identity_preserves_profile_password_role_and_wallet(): void
    {
        $user = User::factory()->create(['google_id' => 'google-sub-123', 'email' => 'old@example.test', 'role' => 'creator', 'country' => 'Original', 'is_active' => true]);
        $user->wallet()->create(['amount' => 147, 'balance_minor' => 14700]);
        $before = $user->getAttributes();
        $code = $this->completeCallback(['email' => 'changed@example.test', 'name' => 'Different Google Name']);
        $response = $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertOk()->assertJsonPath('role', 'creator');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($user->id, $response->json('user.id'));
        foreach (['name', 'email', 'password', 'role', 'country'] as $field) {
            $this->assertSame($before[$field], $user->fresh()->getRawOriginal($field));
        }
        $this->assertEquals(14700, $user->fresh()->wallet->balance_minor);
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        // Use a real protected route and real bearer token, not Sanctum::actingAs.
        $this->withToken($token)->postJson('/api/v1/users/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_matching_email_never_links_an_existing_password_account(): void
    {
        $user = User::factory()->create(['email' => 'google@example.test', 'google_id' => null]);
        $password = $user->password;
        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertRedirect('https://scemory.com/en/auth/google-callback?error=linking_required');
        $this->assertNull($user->fresh()->google_id);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_email_linked_to_another_google_subject_is_never_reused(): void
    {
        User::factory()->create(['email' => 'google@example.test', 'google_id' => 'another-google-sub']);
        $query = $this->start();
        $this->provider();
        $this->getJson('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertStatus(409)->assertJsonPath('error_code', 'identity_conflict');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_missing_mismatched_expired_and_replayed_web_state_are_rejected_before_provider_calls(): void
    {
        $this->getJson('/api/v1/users/google-callback?code=fixture')->assertStatus(400)->assertJsonPath('error_code', 'invalid_state');
        $query = $this->start();
        $this->getJson('/api/v1/users/google-callback?state=wrong&code=fixture')->assertStatus(400)->assertJsonPath('error_code', 'invalid_state');
        $this->getJson('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertStatus(400);
        $query = $this->start();
        $this->travel(11)->minutes();
        $this->getJson('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertStatus(400);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_cancellation_preserves_language_and_consumes_state(): void
    {
        $query = $this->start('ar');
        $url = '/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'error' => 'access_denied']);
        $this->get($url)->assertRedirect('https://scemory.com/ar/auth/google-callback?error=cancelled');
        $this->getJson($url)->assertStatus(400)->assertJsonPath('error_code', 'invalid_state');
    }

    public function test_provider_rejects_invalid_and_expired_authorization_credentials(): void
    {
        foreach ([400, 401] as $status) {
            $query = $this->start();
            $this->provider(tokenStatus: $status);
            $this->getJson('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'invalid-or-expired']))
                ->assertStatus(400)->assertJsonPath('error_code', 'callback_failed');
            Socialite::forgetDrivers();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unverified_or_missing_identity_is_rejected(): void
    {
        foreach ([['email_verified' => false], ['email_verified' => 'true'], ['sub' => ''], ['email' => 'not-an-email']] as $identity) {
            $query = $this->start();
            $this->provider($identity);
            $this->getJson('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
                ->assertStatus(400)->assertJsonPath('error_code', 'identity_invalid');
            Socialite::forgetDrivers();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_userinfo_errors_cannot_authenticate(): void
    {
        $query = $this->start();
        $this->provider(identityStatus: 401);
        $this->getJson('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertStatus(400);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_delivery_is_single_use_session_bound_and_expires(): void
    {
        User::factory()->create(['google_id' => 'google-sub-123', 'is_active' => true]);
        $code = $this->completeCallback();
        $key = 'google-web-delivery:'.hash('sha256', $code);
        $delivery = cache()->get($key);
        cache()->put($key, $delivery + ['unused' => true], 300);
        $this->app['session']->regenerate();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertStatus(422)->assertJsonPath('error_code', 'invalid_state');
        $delivery['session_hash'] = hash('sha256', $this->app['session']->getId());
        cache()->put($key, $delivery, 300);
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertOk();
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Socialite::forgetDrivers();
        $code = $this->completeCallback();
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertStatus(422);
    }

    public function test_csrf_is_required_on_web_exchange(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->postJson('/api/v1/users/google-exchange', ['code' => str_repeat('a', 64)])->assertStatus(419);
    }

    public function test_web_exchange_accepts_valid_csrf_and_revoked_tokens_cannot_access_protected_routes(): void
    {
        User::factory()->create(['google_id' => 'google-sub-123', 'is_active' => true]);
        $code = $this->completeCallback();
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $response = $this->withHeader('X-CSRF-TOKEN', $this->app['session']->token())
            ->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertOk();
        $this->withToken($response->json('token'))->postJson('/api/v1/users/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))->getJson('/api/v1/users/profile')->assertUnauthorized();
    }

    public function test_repeated_google_registration_uses_the_same_account(): void
    {
        $code = $this->completeCallback();
        $first = $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertOk();
        Socialite::forgetDrivers();
        $code = $this->completeCallback(['name' => 'Changed Google Name']);
        $second = $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertOk();
        $this->assertSame($first->json('user.id'), $second->json('user.id'));
        $this->assertSame('Google User', $second->json('user.name'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_schema_upgrade_preserves_existing_accounts_and_password_hashes(): void
    {
        $migration = require database_path('migrations/2026_10_22_000001_add_google_identity_to_users.php');
        $migration->down();
        $user = User::factory()->create(['role' => 'creator', 'is_active' => false]);
        $before = $user->getAttributes();
        $migration->up();
        foreach (['email', 'name', 'password', 'role', 'is_active'] as $field) {
            $this->assertEquals($before[$field], $user->fresh()->getRawOriginal($field));
        }
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_suspension_is_enforced_at_callback_and_again_at_exchange(): void
    {
        $user = User::factory()->create(['google_id' => 'google-sub-123', 'is_active' => true]);
        $code = $this->completeCallback();
        $user->update(['is_active' => false]);
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertForbidden();
        Socialite::forgetDrivers();
        $query = $this->start();
        $this->provider();
        $this->getJson('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))->assertForbidden();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_concurrent_email_claim_does_not_link_or_create_duplicates(): void
    {
        $code = $this->completeCallback();
        User::factory()->create(['email' => 'google@example.test']);
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])
            ->assertStatus(409)->assertJsonPath('error_code', 'linking_required');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_same_display_name_is_safe_for_new_accounts(): void
    {
        User::factory()->create(['name' => 'Google User', 'email' => 'other@example.test']);
        $code = $this->completeCallback();
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertOk();
        $this->assertDatabaseCount('users', 2);
    }

    public function test_configuration_and_untrusted_redirects_fail_safely(): void
    {
        config()->set('services.google.client_secret', null);
        $this->getJson('/api/v1/users/google-login')->assertStatus(503)->assertJsonPath('error_code', 'unavailable');
        config()->set('services.google.client_secret', 'fixture');
        config()->set('app.env', 'production');
        config()->set('services.google.redirect', 'http://scemory.com/api/v1/users/google-callback');
        $this->getJson('/api/v1/users/google-login')->assertStatus(503);
        config()->set('services.google.redirect', 'https://scemory.com/api/v1/users/google-callback');
        $response = $this->getJson('/api/v1/users/google-login?return_url=https://attacker.test&lang=../../attacker');
        $response->assertOk();
        $this->assertStringNotContainsString('attacker.test', $response->json('url'));
    }

    public function test_historical_google_token_name_is_not_account_ownership_proof(): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'role' => 'user',
        ]);
        $user->createToken('google-auth-token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertRedirect('https://scemory.com/en/auth/google-callback?error=linking_required');

        $this->assertNull($user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_legacy_account_with_authoritative_google_subject_mapping_signs_in_directly(): void
    {
        $user = User::factory()->create([
            'email' => 'historical@example.test',
            'google_id' => 'google-sub-123',
            'password' => Hash::make('password'),
            'role' => 'creator',
        ]);
        $user->createToken('google-auth-token');

        $code = $this->completeCallback(['email' => 'changed@example.test']);
        $response = $this->postJson('/api/v1/users/google-exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('role', 'creator');

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('historical@example.test', $user->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('placeholderPasswordProvider')]
    public function test_placeholder_password_hash_is_not_account_ownership_proof(string $placeholder): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'password' => Hash::make($placeholder),
        ]);

        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertRedirect('https://scemory.com/en/auth/google-callback?error=recovery_required');

        $this->postJson('/api/v1/users/google-link-confirm', ['password' => $placeholder])
            ->assertUnprocessable();

        $this->assertNull($user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('legacyPasswordProvider')]
    public function test_legacy_google_only_account_uses_one_time_recovery_and_then_signs_in_directly(?string $legacyPassword): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'facebook_id' => null,
            'password' => $legacyPassword === null ? null : Hash::make($legacyPassword),
            'name' => 'Preserved Name',
            'role' => 'creator',
            'country' => 'Preserved Country',
        ]);
        $user->wallet()->create(['amount' => 321, 'balance_minor' => 32100]);
        $before = $user->getAttributes();

        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertRedirect('https://scemory.com/en/auth/google-callback?error=recovery_required');

        $this->postJson('/api/v1/users/google-recovery-start', [])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('masked_email', 'g*****@example.test');
        $code = null;
        Mail::assertSent(GoogleRecoveryCodeMail::class, function (GoogleRecoveryCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);

        $response = $this->postJson('/api/v1/users/google-recovery-confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('role', 'creator');
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('google-sub-123', $user->fresh()->google_id);
        foreach (['name', 'email', 'password', 'role', 'country'] as $field) {
            $this->assertSame($before[$field], $user->fresh()->getRawOriginal($field));
        }
        $this->assertSame(32100, $user->fresh()->wallet->balance_minor);
        $this->assertDatabaseCount('users', 1);

        $this->postJson('/api/v1/users/google-recovery-confirm', ['code' => $code])
            ->assertUnprocessable()->assertJsonPath('error_code', 'recovery_expired');

        Socialite::forgetDrivers();
        $deliveryCode = $this->completeCallback(['email' => 'changed@example.test']);
        $this->postJson('/api/v1/users/google-exchange', ['code' => $deliveryCode])
            ->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertDatabaseCount('users', 1);
    }

    public static function legacyPasswordProvider(): array
    {
        return [
            'null password' => [null],
            'shared placeholder' => ['password'],
            'legacy placeholder' => ['legacy_google_oauth_user_scemory_default'],
        ];
    }

    public function test_legacy_recovery_rejects_wrong_and_expired_codes(): void
    {
        User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'facebook_id' => null,
            'password' => null,
        ]);
        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']));
        $this->postJson('/api/v1/users/google-recovery-start', [])->assertOk();
        $this->postJson('/api/v1/users/google-recovery-confirm', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'invalid_recovery_code');
        $this->assertNull(User::sole()->google_id);

        $this->travel(11)->minutes();
        $this->postJson('/api/v1/users/google-recovery-confirm', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'recovery_expired');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_legacy_recovery_locks_after_five_wrong_codes(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'facebook_id' => null,
            'password' => null,
        ]);
        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']));
        $this->postJson('/api/v1/users/google-recovery-start', [])->assertOk();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v1/users/google-recovery-confirm', ['code' => '000000'])
                ->assertUnprocessable()->assertJsonPath('error_code', 'invalid_recovery_code');
        }
        $this->postJson('/api/v1/users/google-recovery-confirm', ['code' => '000000'])
            ->assertStatus(429)->assertJsonPath('error_code', 'recovery_attempts_exceeded');

        $code = null;
        Mail::assertSent(GoogleRecoveryCodeMail::class, function (GoogleRecoveryCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->postJson('/api/v1/users/google-recovery-confirm', ['code' => $code])
            ->assertStatus(429)->assertJsonPath('error_code', 'recovery_attempts_exceeded');
        $this->assertNull(User::sole()->google_id);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_recovery_is_not_available_for_password_or_other_provider_accounts(): void
    {
        $passwordUser = User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'password' => Hash::make('real-password'),
        ]);
        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertRedirect('https://scemory.com/en/auth/google-callback?error=linking_required');
        $this->postJson('/api/v1/users/google-recovery-start', [])
            ->assertUnprocessable()->assertJsonPath('error_code', 'recovery_expired');
        $this->assertNull($passwordUser->fresh()->google_id);
        Mail::assertNotSent(GoogleRecoveryCodeMail::class);

        $passwordUser->delete();
        User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'facebook_id' => 'existing-provider-id',
            'password' => null,
        ]);
        Socialite::forgetDrivers();
        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']))
            ->assertRedirect('https://scemory.com/en/auth/google-callback?error=account_login_required');
        $this->assertNull(session('pending_google_link'));
        Mail::assertNotSent(GoogleRecoveryCodeMail::class);
    }

    public function test_recovery_code_is_never_sent_through_a_logging_mailer(): void
    {
        User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'facebook_id' => null,
            'password' => null,
        ]);
        $query = $this->start();
        $this->provider();
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']));
        config()->set('mail.default', 'log');
        $this->postJson('/api/v1/users/google-recovery-start', [])
            ->assertStatus(503)->assertJsonPath('error_code', 'recovery_unavailable');
        Mail::assertNotSent(GoogleRecoveryCodeMail::class);
        $this->assertNull(User::sole()->google_id);
    }

    public static function placeholderPasswordProvider(): array
    {
        return [
            'shared password placeholder' => ['password'],
            'legacy Google placeholder' => ['legacy_google_oauth_user_scemory_default'],
        ];
    }

    public function test_existing_password_user_can_explicitly_link_google_using_password(): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.test',
            'google_id' => null,
            'password' => Hash::make('custom-secure-pass'),
        ]);

        $query = $this->start();
        $this->provider();
        $response = $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']));
        $response->assertRedirect('https://scemory.com/en/auth/google-callback?error=linking_required');
        $this->assertNull($user->fresh()->google_id);

        // Wrong password rejected
        $this->postJson('/api/v1/users/google-link-confirm', ['password' => 'wrong-pass'])
            ->assertStatus(422);
        $this->assertNull($user->fresh()->google_id);

        // Correct password links account and returns token
        $linkResponse = $this->postJson('/api/v1/users/google-link-confirm', ['password' => 'custom-secure-pass'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertNotEmpty($linkResponse->json('token'));
        $this->assertSame('google-sub-123', $user->fresh()->google_id);

        Socialite::forgetDrivers();
        $code = $this->completeCallback();
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_authenticated_user_can_link_and_unlink_google_account(): void
    {
        $user = User::factory()->create([
            'email' => 'auth-link@example.test',
            'google_id' => null,
            'password' => Hash::make('secure-pass'),
        ]);

        $this->actingAs($user, 'sanctum');
        $startResponse = $this->getJson('/api/v1/users/google-link-start?lang=en');
        $startResponse->assertOk();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        parse_str(parse_url($startResponse->json('url'), PHP_URL_QUERY), $query);
        Socialite::forgetDrivers();

        $this->provider(['sub' => 'new-linked-sub', 'email' => 'auth-link@example.test']);
        $callbackResponse = $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $query['state'], 'code' => 'fixture']));
        $callbackResponse->assertRedirect('https://scemory.com/en/profile?google_linked=1');

        $this->assertSame('new-linked-sub', $user->fresh()->google_id);

        // Unlink works when password exists
        $this->actingAs($user->fresh(), 'sanctum');
        $this->postJson('/api/v1/users/google-unlink')->assertOk();
        $this->assertNull($user->fresh()->google_id);

        // Unlink fails if no password and no facebook
        $user->fresh()->forceFill(['google_id' => 'new-linked-sub', 'password' => null])->save();
        $this->actingAs($user->fresh(), 'sanctum');
        $this->postJson('/api/v1/users/google-unlink')->assertStatus(422);
        $this->assertSame('new-linked-sub', $user->fresh()->google_id);
    }
}
