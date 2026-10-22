<?php

namespace App\Http\Controllers\api\auth;

use App\Exceptions\FacebookAuthException;
use App\Http\Controllers\Controller;
use App\Services\FacebookAccountService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class FacebookAuthController extends Controller
{
    private const DELIVERY_SECONDS = 900;

    public function __construct(private readonly FacebookAccountService $accounts) {}

    public function login(Request $request)
    {
        $lang = $this->lang($request->query('lang'));
        if (! $this->configured()) {
            return $this->failure($request, 'unavailable', $lang, 503);
        }
        try {
            $attempt = $this->withFacebookState($request, null, function () use ($request) {
                $url = Socialite::driver('facebook')->setScopes(['email'])->redirect()->getTargetUrl();

                return ['url' => $url, 'state' => $request->session()->get('state')];
            });
            $request->session()->put('facebook_oauth', [
                'state' => $attempt['state'], 'lang' => $lang, 'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            return ($request->expectsJson() ? response()->json(['status' => 'success', 'url' => $attempt['url']]) : redirect()->away($attempt['url']))
                ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
        } catch (Throwable $e) {
            Log::warning('Facebook OAuth start failed.', ['exception' => $e::class]);

            return $this->failure($request, 'unavailable', $lang, 503);
        }
    }

    public function callback(Request $request)
    {
        $attempt = $request->session()->pull('facebook_oauth');
        $lang = $this->lang($attempt['lang'] ?? null);
        try {
            if (! $this->configured()) {
                throw new FacebookAuthException('unavailable', 503);
            }
            $expected = $attempt['state'] ?? null;
            $actual = $request->query('state');
            if (! is_array($attempt) || ($attempt['expires_at'] ?? 0) <= now()->timestamp
                || ! is_string($expected) || ! is_string($actual) || ! hash_equals($expected, $actual)) {
                throw new FacebookAuthException('invalid_state');
            }
            if ($request->has('error')) {
                throw new FacebookAuthException($request->query('error') === 'access_denied' ? 'cancelled' : 'callback_failed');
            }
            if (! is_string($request->query('code')) || $request->query('code') === '') {
                throw new FacebookAuthException('callback_failed');
            }
            $facebook = $this->withFacebookState($request, $expected,
                fn () => Socialite::driver('facebook')->fields(['id', 'name', 'email', 'picture'])->user()
            );
            $identity = $this->accounts->verifiedIdentity($facebook);
            $this->accounts->existingAccount($identity);
            $request->session()->regenerate();
            $code = Str::random(64);
            Cache::put($this->deliveryKey($code), [
                'identity' => $identity, 'session_hash' => hash('sha256', $request->session()->getId()),
                'expires_at' => now()->addSeconds(self::DELIVERY_SECONDS)->timestamp,
            ], self::DELIVERY_SECONDS);

            return $this->redirectResult($lang, ['code' => $code]);
        } catch (FacebookAuthException $e) {
            return $this->failure($request, $e->errorCode, $lang, $e->status);
        } catch (InvalidStateException) {
            return $this->failure($request, 'invalid_state', $lang);
        } catch (Throwable $e) {
            Log::warning('Facebook OAuth callback failed.', ['exception' => $e::class]);

            return $this->failure($request, 'callback_failed', $lang);
        }
    }

    public function exchange(Request $request)
    {
        $code = $request->validate([
            'code' => ['required', 'string', 'size:64', 'regex:/\A[A-Za-z0-9]+\z/'],
        ])['code'];
        $key = $this->deliveryKey($code);
        try {
            return Cache::lock($key.':lock', 15)->block(3, function () use ($request, $key) {
                $delivery = Cache::get($key);
                if (! is_array($delivery) || ($delivery['expires_at'] ?? 0) <= now()->timestamp) {
                    throw new FacebookAuthException('expired', 422);
                }
                if (! hash_equals($delivery['session_hash'], hash('sha256', $request->session()->getId()))) {
                    throw new FacebookAuthException('invalid_state', 422);
                }
                $user = $this->accounts->registerIdentity($delivery['identity']);
                $user->forceFill(['last_login_at' => now()])->save();
                $token = $user->createToken('facebook-auth-token')->plainTextToken;
                Cache::forget($key);

                return response()->json([
                    'status' => 'success', 'token' => $token, 'role' => $user->role,
                    'is_profile_complete' => ! empty($user->phone) && ! empty($user->country)
                        && ! empty($user->position) && ! empty($user->date_of_birth),
                    'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                        'role' => $user->role, 'facebook_avatar_url' => $user->facebook_avatar_url],
                ])->header('Cache-Control', 'no-store, private');
            });
        } catch (FacebookAuthException $e) {
            return $this->jsonError($e->errorCode, $e->status);
        } catch (LockTimeoutException) {
            return $this->jsonError('busy', 409);
        } catch (Throwable $e) {
            Log::warning('Facebook token exchange failed.', ['exception' => $e::class]);

            return $this->jsonError('callback_failed', 500);
        }
    }

    private function withFacebookState(Request $request, ?string $state, callable $operation): mixed
    {
        // Socialite's providers share a hardcoded "state" key. Preserve any other
        // provider's pending request and namespace Facebook's state in our session.
        $otherState = $request->session()->get('state');
        $request->session()->put('state', $state);
        try {
            return $operation();
        } finally {
            if ($otherState === null) {
                $request->session()->forget('state');
            } else {
                $request->session()->put('state', $otherState);
            }
        }
    }

    private function deliveryKey(string $code): string
    {
        return 'facebook-delivery:'.hash('sha256', $code);
    }

    private function configured(): bool
    {
        foreach (['client_id', 'client_secret'] as $key) {
            if (! is_string(config('services.facebook.'.$key)) || trim(config('services.facebook.'.$key)) === '') {
                return false;
            }
        }
        $redirect = (string) config('services.facebook.redirect');

        return $this->safeUrl($redirect) && parse_url($redirect, PHP_URL_PATH) === '/api/v1/users/facebook-callback'
            && $this->safeUrl((string) config('app.frontend_url'));
    }

    private function safeUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS)
            || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) {
            return false;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https'
            || (! app()->isProduction() && parse_url($url, PHP_URL_SCHEME) === 'http'
                && in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true));
    }

    private function lang(mixed $lang): string
    {
        return is_string($lang) && in_array(strtolower($lang), ['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'], true)
            ? strtolower($lang) : 'en';
    }

    private function failure(Request $request, string $error, string $lang, int $status = 400)
    {
        return $request->expectsJson() ? $this->jsonError($error, $status) : $this->redirectResult($lang, ['error' => $error]);
    }

    private function jsonError(string $error, int $status)
    {
        return response()->json(['status' => 'error', 'error_code' => $error, 'message_key' => 'facebookAuth.'.$error], $status)
            ->header('Cache-Control', 'no-store, private');
    }

    private function redirectResult(string $lang, array $query)
    {
        $base = (string) config('app.frontend_url');
        if (! $this->safeUrl($base)) {
            return $this->jsonError('unavailable', 503);
        }

        return redirect()->away(rtrim($base, '/')."/{$lang}/auth/facebook-callback?".http_build_query($query))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
}
