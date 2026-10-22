<?php

namespace Tests\Feature\Api;

use App\Mail\WelcomeMail;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

class GoogleMobileExchangeTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFIER = 'vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set('app.frontend_url', 'https://scemory.com');
        config()->set('services.google.client_id', 'fixture-client');
        config()->set('services.google.client_secret', 'fixture-secret');
        config()->set('services.google.redirect', 'https://scemory.com/api/v1/users/google-callback');
    }

    private function start(string $lang = 'en'): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '=');
        $response = $this->get('/api/v1/users/google-login?'.http_build_query([
            'mobile' => 1, 'lang' => $lang, 'code_challenge' => $challenge, 'return_url' => 'https://attacker.test',
        ]));
        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/auth?');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('fixture-client', $query['client_id']);
        $this->assertSame(config('services.google.redirect'), $query['redirect_uri']);
        $this->assertStringStartsWith('scemory-mobile-v1.', $query['state']);
        $this->assertStringNotContainsString('attacker.test', $response->headers->get('Location'));
        Socialite::forgetDrivers();

        return $query['state'];
    }

    private function provider(bool $fail = false): void
    {
        $responses = new MockHandler([
            new Response($fail ? 400 : 200, [], json_encode(['access_token' => 'server-only-token'])),
            new Response(200, [], json_encode(['sub' => 'mobile-google-sub', 'email' => 'mobile@example.test', 'email_verified' => true, 'name' => 'Mobile User'])),
        ]);
        Socialite::extend('google', fn ($app) => (new GoogleProvider($app['request'], 'fixture-client', 'fixture-secret', config('services.google.redirect')))
            ->setHttpClient(new Client(['handler' => HandlerStack::create($responses)])));
    }

    private function delivery(string $lang = 'en'): string
    {
        $state = $this->start($lang);
        $this->provider();
        $response = $this->get('/api/v1/users/google-callback?'.http_build_query(['code' => 'fixture', 'state' => $state]));
        $response->assertRedirectContains('https://scemory.com/mobile/auth/callback?');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame($lang, $query['lang']);
        $this->assertArrayNotHasKey('token', $query);
        $this->assertArrayNotHasKey('email', $query);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $query['code']);

        return $query['code'];
    }

    private function exchange(string $code, array $profile = [], string $verifier = self::VERIFIER)
    {
        return $this->postJson('/api/v1/users/google-mobile-exchange', ['code' => $code, 'code_verifier' => $verifier] + $profile);
    }

    public function test_existing_mobile_user_returns_to_verified_app_link_and_exchanges_code_once(): void
    {
        $user = User::factory()->create(['google_id' => 'mobile-google-sub', 'email' => 'mobile@example.test', 'is_active' => true]);
        $code = $this->delivery('ar');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->exchange($code)->assertOk()->assertJsonPath('user.id', $user->id);
        $this->exchange($code)->assertStatus(422);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_new_mobile_account_signs_in_immediately_without_profile_or_password(): void
    {
        $code = $this->delivery();
        $this->exchange($code, ['country' => 'fabricated', 'date_of_birth' => '2999-01-01'])
            ->assertOk()->assertJsonPath('role', 'user');
        $this->assertDatabaseHas('users', [
            'google_id' => 'mobile-google-sub',
            'email' => 'mobile@example.test',
            'password' => null,
            'country' => null,
            'date_of_birth' => null,
        ]);
        Mail::assertQueued(WelcomeMail::class);
    }

    public function test_mobile_google_cancellation_returns_a_safe_error_to_the_app(): void
    {
        $state = $this->start('fr');
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $state, 'error' => 'access_denied']))
            ->assertRedirect('https://scemory.com/mobile/auth/callback?lang=fr&error=cancelled');
    }

    public function test_expired_and_replayed_mobile_oauth_states_are_rejected(): void
    {
        $state = $this->start('de');
        $this->travel(11)->minutes();
        $url = '/api/v1/users/google-callback?'.http_build_query(['state' => $state, 'code' => 'fixture']);
        $this->get($url)->assertRedirect('https://scemory.com/mobile/auth/callback?lang=de&error=expired_state');
        $this->travelBack();
        $state = $this->start('de');
        $this->provider();
        $url = '/api/v1/users/google-callback?'.http_build_query(['state' => $state, 'code' => 'fixture']);
        $this->get($url)->assertRedirectContains('code=');
        $this->get($url)->assertRedirect('https://scemory.com/mobile/auth/callback?lang=de&error=invalid_state');
    }

    public function test_expired_or_unknown_delivery_code_is_rejected_without_issuing_a_token(): void
    {
        $code = $this->delivery();
        $this->travel(6)->minutes();
        $this->exchange($code)->assertStatus(422);
        $this->exchange(str_repeat('a', 64))->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_delivery_code_is_bound_to_the_app_pkce_verifier_and_cannot_use_web_exchange(): void
    {
        User::factory()->create(['google_id' => 'mobile-google-sub', 'is_active' => true]);
        $code = $this->delivery();
        $this->exchange($code, [], str_repeat('x', 43))->assertStatus(422)->assertJsonPath('error_code', 'invalid_state');
        $this->postJson('/api/v1/users/google-exchange', ['code' => $code])->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->exchange($code)->assertOk();
    }

    public function test_mobile_provider_callback_failure_returns_no_sensitive_details(): void
    {
        $state = $this->start();
        $this->provider(fail: true);
        $this->get('/api/v1/users/google-callback?'.http_build_query(['state' => $state, 'code' => 'invalid']))
            ->assertRedirect('https://scemory.com/mobile/auth/callback?lang=en&error=callback_failed');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_malformed_mobile_state_and_missing_challenge_cannot_downgrade_to_web(): void
    {
        $this->getJson('/api/v1/users/google-login?mobile=1')->assertUnprocessable();
        $this->get('/api/v1/users/google-callback?state=scemory-mobile-v1.invalid&code=fixture')
            ->assertRedirect('https://scemory.com/mobile/auth/callback?lang=en&error=invalid_state');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_configuration_returns_a_localizable_error_to_the_app(): void
    {
        config()->set('services.google.client_id', null);
        $this->get('/api/v1/users/google-login?mobile=1&lang=ar')
            ->assertRedirect('https://scemory.com/mobile/auth/callback?lang=ar&error=unavailable');
    }
}
