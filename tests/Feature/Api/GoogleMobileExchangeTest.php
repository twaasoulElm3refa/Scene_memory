<?php

namespace Tests\Feature\Api;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class GoogleMobileExchangeTest extends TestCase
{
    use RefreshDatabase;

    private const CODE_VERIFIER = 'vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv';

    public function test_existing_mobile_user_returns_to_verified_app_link_and_exchanges_code_once(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.test',
            'role' => 'user',
            'is_active' => true,
        ]);
        $provider = $this->mobileProvider('existing@example.test', 'Existing User');

        Socialite::shouldReceive('driver')->with('google')->twice()->andReturn($provider);

        $login = $this->get($this->mobileLoginUrl('ar').'&return_url=https://attacker.test');
        $login->assertRedirectContains('https://accounts.google.test/o/oauth2');
        $state = $this->parseRedirectQuery($login->headers->get('Location'))['state'] ?? null;
        $this->assertNotEmpty($state);
        $this->assertStringNotContainsString('attacker.test', $login->headers->get('Location'));

        $callback = $this->get('/api/v1/users/google-callback?'.http_build_query([
            'code' => 'google-code',
            'state' => $state,
        ]));

        $callback->assertRedirectContains('https://scemory.com/mobile/auth/callback?');
        $appLink = $this->parseRedirectQuery($callback->headers->get('Location'));

        $this->assertSame('ar', $appLink['lang']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $appLink['code']);
        $this->assertArrayNotHasKey('token', $appLink);
        $this->assertArrayNotHasKey('email', $appLink);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $exchange = $this->postJson('/api/v1/users/google-mobile-exchange', [
            'code' => $appLink['code'],
            'code_verifier' => self::CODE_VERIFIER,
        ]);

        $exchange->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('role', 'user')
            ->assertJsonPath('user.email', 'existing@example.test');
        $this->assertNotEmpty($exchange->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/v1/users/google-mobile-exchange', [
            'code' => $appLink['code'],
            'code_verifier' => self::CODE_VERIFIER,
        ])
            ->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame($user->id, User::where('email', 'existing@example.test')->value('id'));
    }

    public function test_mobile_callback_creates_a_new_user_without_putting_identity_in_app_link(): void
    {
        Mail::fake();
        DB::table('licence_types')->insert([
            'id' => 1,
            'name' => 'Free',
            'price' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $provider = $this->mobileProvider('new@example.test', 'New User');

        Socialite::shouldReceive('driver')->with('google')->twice()->andReturn($provider);

        $login = $this->get($this->mobileLoginUrl('en'));
        $login->assertRedirect();
        $state = $this->parseRedirectQuery($login->headers->get('Location'))['state'] ?? null;

        $callback = $this->get('/api/v1/users/google-callback?'.http_build_query([
            'code' => 'google-code',
            'state' => $state,
        ]));
        $appLink = $this->parseRedirectQuery($callback->headers->get('Location'));

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.test',
            'name' => 'New User',
            'is_active' => true,
        ]);
        $this->assertArrayNotHasKey('token', $appLink);
        $this->assertArrayNotHasKey('email', $appLink);
        Mail::assertQueued(WelcomeMail::class);

        $this->postJson('/api/v1/users/google-mobile-exchange', [
            'code' => $appLink['code'],
            'code_verifier' => self::CODE_VERIFIER,
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.test');
    }

    public function test_mobile_google_cancellation_returns_a_safe_error_to_the_app(): void
    {
        $provider = $this->mobileProvider();

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $login = $this->get($this->mobileLoginUrl('fr'));
        $login->assertRedirect();
        $state = $this->parseRedirectQuery($login->headers->get('Location'))['state'] ?? null;

        $callback = $this->get('/api/v1/users/google-callback?'.http_build_query([
            'error' => 'access_denied',
            'state' => $state,
        ]));

        $callback->assertRedirect('https://scemory.com/mobile/auth/callback?lang=fr&error=cancelled');
    }

    public function test_expired_and_replayed_mobile_oauth_states_are_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'state@example.test',
            'is_active' => true,
        ]);
        $provider = $this->mobileProvider($user->email, $user->name);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $login = $this->get($this->mobileLoginUrl('de'));
        $login->assertRedirect();
        $state = $this->parseRedirectQuery($login->headers->get('Location'))['state'] ?? null;

        $this->travel(11)->minutes();

        $this->get('/api/v1/users/google-callback?'.http_build_query([
            'code' => 'google-code',
            'state' => $state,
        ]))->assertRedirect('https://scemory.com/mobile/auth/callback?lang=de&error=expired_state');

        $this->travelBack();

        $provider = $this->mobileProvider($user->email, $user->name);
        Socialite::shouldReceive('driver')->with('google')->twice()->andReturn($provider);
        $login = $this->get($this->mobileLoginUrl('de'));
        $login->assertRedirect();
        $state = $this->parseRedirectQuery($login->headers->get('Location'))['state'] ?? null;

        $callbackUrl = '/api/v1/users/google-callback?'.http_build_query([
            'code' => 'google-code',
            'state' => $state,
        ]);
        $this->get($callbackUrl)->assertRedirectContains('code=');
        $this->get($callbackUrl)
            ->assertRedirect('https://scemory.com/mobile/auth/callback?lang=de&error=invalid_state');
    }

    public function test_expired_or_unknown_delivery_code_is_rejected_without_issuing_a_token(): void
    {
        $code = str_repeat('a', 64);
        Cache::put('google-mobile-delivery:'.hash('sha256', $code), [
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'code_challenge' => $this->codeChallenge(),
        ], now()->subSecond());

        $this->postJson('/api/v1/users/google-mobile-exchange', [
            'code' => $code,
            'code_verifier' => self::CODE_VERIFIER,
        ])
            ->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_delivery_code_is_bound_to_the_app_pkce_verifier(): void
    {
        $code = str_repeat('b', 64);
        Cache::put('google-mobile-delivery:'.hash('sha256', $code), [
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'code_challenge' => $this->codeChallenge(),
        ], now()->addMinutes(2));

        $this->postJson('/api/v1/users/google-mobile-exchange', [
            'code' => $code,
            'code_verifier' => str_repeat('x', 43),
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This sign in link does not belong to this app session.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_provider_callback_failure_returns_to_the_app_without_details(): void
    {
        $provider = $this->mobileProvider(shouldFail: true);
        Socialite::shouldReceive('driver')->with('google')->twice()->andReturn($provider);

        $login = $this->get($this->mobileLoginUrl('en'));
        $state = $this->parseRedirectQuery($login->headers->get('Location'))['state'] ?? null;

        $this->get('/api/v1/users/google-callback?'.http_build_query([
            'code' => 'bad-google-code',
            'state' => $state,
        ]))->assertRedirect('https://scemory.com/mobile/auth/callback?lang=en&error=callback_failed');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_web_google_callback_keeps_the_existing_frontend_redirect_flow(): void
    {
        config()->set('app.frontend_url', 'https://scemory.com');
        $user = User::factory()->create([
            'email' => 'web@example.test',
            'role' => 'user',
            'is_active' => true,
        ]);
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($this->googleUser('web@example.test', $user->name));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->withCookie('oauth_lang', 'en')
            ->get('/api/v1/users/google-callback?code=google-code');

        $response->assertRedirectContains('https://scemory.com/en/auth/google-callback?token=');
        $this->assertStringNotContainsString('/mobile/auth/callback', $response->headers->get('Location'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_real_google_provider_includes_the_mobile_state_in_its_authorization_url(): void
    {
        config()->set('services.google.client_id', 'test-google-client');
        config()->set('services.google.client_secret', 'test-google-secret');
        config()->set('services.google.redirect', 'https://scemory.com/api/v1/users/google-callback');

        $response = $this->get($this->mobileLoginUrl('en'));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/auth?');
        $query = $this->parseRedirectQuery($response->headers->get('Location'));

        $this->assertSame('test-google-client', $query['client_id']);
        $this->assertSame('https://scemory.com/api/v1/users/google-callback', $query['redirect_uri']);
        $this->assertStringStartsWith('scemory-mobile-v1.', $query['state']);
    }

    private function mobileProvider(
        string $email = 'mobile@example.test',
        string $name = 'Mobile User',
        bool $shouldFail = false
    ): Mockery\MockInterface {
        $state = null;
        $provider = Mockery::mock();

        $provider->shouldReceive('stateless')->atLeast()->once()->andReturnSelf();
        $provider->shouldReceive('with')->once()->with(Mockery::on(
            function (array $parameters) use (&$state): bool {
                $state = $parameters['state'] ?? null;

                return is_string($state) && str_starts_with($state, 'scemory-mobile-v1.');
            }
        ))->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturnUsing(
            function () use (&$state): RedirectResponse {
                return new RedirectResponse(
                    'https://accounts.google.test/o/oauth2?'.http_build_query(['state' => $state])
                );
            }
        );
        if ($shouldFail) {
            $provider->shouldReceive('user')->once()->andThrow(new \RuntimeException('Provider rejected callback.'));
        } else {
            $provider->shouldReceive('user')->zeroOrMoreTimes()->andReturn($this->googleUser($email, $name));
        }

        return $provider;
    }

    private function googleUser(string $email, string $name): Mockery\MockInterface
    {
        $googleUser = Mockery::mock();
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);

        return $googleUser;
    }

    private function parseRedirectQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function mobileLoginUrl(string $lang): string
    {
        return '/api/v1/users/google-login?'.http_build_query([
            'mobile' => 1,
            'lang' => $lang,
            'code_challenge' => $this->codeChallenge(),
        ]);
    }

    private function codeChallenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', self::CODE_VERIFIER, true)), '+/', '-_'), '=');
    }
}
