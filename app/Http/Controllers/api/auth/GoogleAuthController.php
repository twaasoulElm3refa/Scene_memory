<?php

namespace App\Http\Controllers\api\auth;

use App\Exceptions\GoogleAuthException;
use App\Http\Controllers\Controller;
use App\Services\GoogleAccountService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    private const MOBILE_STATE_PREFIX = 'scemory-mobile-v1.';

    public function __construct(private readonly GoogleAccountService $accounts) {}

    public function googleLogin(Request $request)
    {
        $lang = $this->resolveLang($request);
        $mobile = $request->boolean('mobile') ? ['lang' => $lang] : null;
        if (! $this->configured()) {
            return $this->failure($request, 'unavailable', $lang, $mobile, 503);
        }

        try {
            $provider = Socialite::driver('google');
            if ($request->boolean('mobile')) {
                $challenge = $request->validate([
                    'code_challenge' => ['required', 'string', 'size:43', 'regex:/\A[A-Za-z0-9_-]+\z/'],
                ])['code_challenge'];
                // Native clients cannot share the browser's Laravel session. Their
                // encrypted, single-use state and delivery verifier bind the flow instead.
                $provider->stateless()->with(['state' => $this->createMobileOAuthState($lang, $challenge)]);
            } else {
                $request->session()->regenerate();
                $request->session()->put('google_oauth', ['lang' => $lang, 'expires_at' => now()->addMinutes(10)->timestamp]);
                $provider->enablePKCE();
            }
            $url = $provider->redirect()->getTargetUrl();

            return ($request->expectsJson() ? response()->json(['status' => 'success', 'url' => $url]) : redirect()->away($url))
                ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Google OAuth start failed.', ['exception' => $e::class]);

            return $this->failure($request, 'unavailable', $lang, $mobile, 503);
        }
    }

    public function googleCallback(Request $request)
    {
        $mobile = $this->consumeMobileOAuthState($request->query('state'));
        $lang = $mobile['lang'] ?? $this->resolveLang($request);

        try {
            if (! $this->configured()) {
                throw new GoogleAuthException('unavailable', 503);
            }
            if ($mobile !== null) {
                if (! $mobile['valid']) {
                    throw new GoogleAuthException($mobile['error']);
                }
            } else {
                $attempt = $request->session()->pull('google_oauth');
                $lang = $this->normalizeLang($attempt['lang'] ?? $lang);
                $expected = $request->session()->get('state');
                $actual = $request->query('state');
                if (! is_array($attempt) || ($attempt['expires_at'] ?? 0) <= now()->timestamp
                    || ! is_string($expected) || ! is_string($actual) || ! hash_equals($expected, $actual)) {
                    $request->session()->forget(['state', 'code_verifier']);
                    throw new GoogleAuthException('invalid_state');
                }
            }
            if ($request->has('error')) {
                $request->session()->forget(['state', 'code_verifier']);
                throw new GoogleAuthException($request->query('error') === 'access_denied' ? 'cancelled' : 'oauth_failed');
            }
            if (! is_string($request->query('code')) || $request->query('code') === '') {
                throw new GoogleAuthException('callback_failed');
            }

            $provider = Socialite::driver('google');
            $google = $mobile !== null ? $provider->stateless()->user() : $provider->enablePKCE()->user();
            $identity = $this->accounts->verifiedIdentity($google);
            $this->accounts->existingAccount($identity);

            $code = Str::random(64);
            $delivery = ['identity' => $identity];
            if ($mobile !== null) {
                $delivery['code_challenge'] = $mobile['code_challenge'];
            } else {
                $request->session()->regenerate();
                $delivery['session_hash'] = hash('sha256', $request->session()->getId());
            }
            Cache::put($this->deliveryKey($code, $mobile !== null), $delivery, now()->addMinutes(5));

            return $this->redirectResult($lang, ['code' => $code], $mobile !== null);
        } catch (GoogleAuthException $e) {
            return $this->failure($request, $e->errorCode, $lang, $mobile, $e->status);
        } catch (InvalidStateException) {
            return $this->failure($request, 'invalid_state', $lang, $mobile);
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed.', ['exception' => $e::class]);

            return $this->failure($request, 'callback_failed', $lang, $mobile);
        }
    }

    public function exchangeWebCode(Request $request)
    {
        return $this->exchange($request, false);
    }

    public function exchangeMobileCode(Request $request)
    {
        return $this->exchange($request, true);
    }

    private function exchange(Request $request, bool $mobile)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:64', 'regex:/\A[A-Za-z0-9]+\z/'],
            'code_verifier' => [$mobile ? 'required' : 'nullable', 'string', 'min:43', 'max:128', 'regex:/\A[A-Za-z0-9._~-]+\z/'],
        ]);
        $key = $this->deliveryKey($data['code'], $mobile);

        try {
            return Cache::lock($key.':lock', 10)->block(3, function () use ($request, $mobile, $key) {
                $delivery = Cache::get($key);
                if (! is_array($delivery) || ! isset($delivery['identity'])) {
                    throw new GoogleAuthException('expired_state', 422);
                }
                $expected = $mobile ? ($delivery['code_challenge'] ?? '') : ($delivery['session_hash'] ?? '');
                $actual = $mobile
                    ? rtrim(strtr(base64_encode(hash('sha256', (string) $request->input('code_verifier'), true)), '+/', '-_'), '=')
                    : hash('sha256', $request->session()->getId());
                if ($expected === '' || ! hash_equals($expected, $actual)) {
                    throw new GoogleAuthException('invalid_state', 422);
                }

                $user = $this->accounts->existingAccount($delivery['identity']);
                if (! $user && (! $request->filled('country') || ! $request->filled('date_of_birth'))) {
                    return response()->json(['status' => 'profile_required'], 202)->header('Cache-Control', 'no-store, private');
                }
                $profile = $user ? [] : $request->validate([
                    'country' => ['required', 'string', 'max:255'],
                    'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                ]);
                $user = $this->accounts->complete($delivery['identity'], $profile);
                Cache::forget($key);

                return response()->json([
                    'status' => 'success',
                    'token' => $user->createToken($mobile ? 'google-mobile-auth-token' : 'google-auth-token')->plainTextToken,
                    'role' => $user->role,
                    'is_profile_complete' => ! empty($user->phone) && ! empty($user->country)
                        && ! empty($user->position) && ! empty($user->date_of_birth),
                    'user' => [
                        'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                        'role' => $user->role, 'google_avatar_url' => $user->google_avatar_url,
                    ],
                ])->header('Cache-Control', 'no-store, private');
            });
        } catch (GoogleAuthException $e) {
            return $this->jsonError($e->errorCode, $e->status);
        } catch (LockTimeoutException) {
            return $this->jsonError('busy', 409);
        }
    }

    private function deliveryKey(string $code, bool $mobile): string
    {
        return ($mobile ? 'google-mobile-delivery:' : 'google-web-delivery:').hash('sha256', $code);
    }

    private function createMobileOAuthState(string $lang, string $codeChallenge): string
    {
        $nonce = Str::random(64);
        $expiresAt = now()->addMinutes(10);
        $nonceHash = hash('sha256', $nonce);
        Cache::put("google-mobile-state:{$nonceHash}", $nonceHash, $expiresAt);

        return self::MOBILE_STATE_PREFIX.Crypt::encryptString(json_encode([
            'channel' => 'android', 'nonce' => $nonce, 'lang' => $lang,
            'code_challenge' => $codeChallenge, 'expires_at' => $expiresAt->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function consumeMobileOAuthState(mixed $state): ?array
    {
        if (! is_string($state) || ! str_starts_with($state, self::MOBILE_STATE_PREFIX)) {
            return null;
        }
        $fallback = ['valid' => false, 'lang' => 'en', 'error' => 'invalid_state'];
        try {
            $payload = json_decode(Crypt::decryptString(substr($state, strlen(self::MOBILE_STATE_PREFIX))), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $fallback;
        }
        if (($payload['channel'] ?? null) !== 'android' || ! is_string($payload['nonce'] ?? null)
            || ! is_string($payload['code_challenge'] ?? null)
            || ! preg_match('/\A[A-Za-z0-9_-]{43}\z/', $payload['code_challenge'])) {
            return $fallback;
        }
        $lang = $this->normalizeLang($payload['lang'] ?? 'en');
        $nonceHash = hash('sha256', $payload['nonce']);
        if (! is_int($payload['expires_at'] ?? null) || $payload['expires_at'] <= now()->timestamp) {
            Cache::forget("google-mobile-state:{$nonceHash}");

            return ['valid' => false, 'lang' => $lang, 'error' => 'expired_state'];
        }
        try {
            $stored = Cache::lock("google-mobile-state-lock:{$nonceHash}", 10)
                ->block(3, fn () => Cache::pull("google-mobile-state:{$nonceHash}"));
        } catch (LockTimeoutException) {
            $stored = null;
        }
        if (! is_string($stored) || ! hash_equals($nonceHash, $stored)) {
            return ['valid' => false, 'lang' => $lang, 'error' => 'invalid_state'];
        }

        return ['valid' => true, 'lang' => $lang, 'code_challenge' => $payload['code_challenge']];
    }

    private function configured(): bool
    {
        foreach (['client_id', 'client_secret'] as $key) {
            if (! is_string(config('services.google.'.$key)) || trim(config('services.google.'.$key)) === '') {
                return false;
            }
        }
        $redirect = (string) config('services.google.redirect');

        return $this->safeConfiguredUrl($redirect)
            && parse_url($redirect, PHP_URL_PATH) === '/api/v1/users/google-callback'
            && $this->safeConfiguredUrl((string) config('app.frontend_url'))
            && $this->safeConfiguredUrl((string) config('services.google.mobile_app_link'));
    }

    private function safeConfiguredUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_USER)
            || parse_url($url, PHP_URL_PASS) || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) {
            return false;
        }
        if (parse_url($url, PHP_URL_SCHEME) === 'https') {
            return true;
        }

        return ! app()->isProduction() && parse_url($url, PHP_URL_SCHEME) === 'http'
            && in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true);
    }

    private function failure(Request $request, string $error, string $lang, ?array $mobile, int $status = 400)
    {
        return $request->expectsJson() ? $this->jsonError($error, $status)
            : $this->redirectResult($lang, ['error' => $error], $mobile !== null);
    }

    private function jsonError(string $error, int $status)
    {
        // The existing Vue i18n dictionaries translate this stable key.
        return response()->json(['status' => 'error', 'error_code' => $error, 'message_key' => 'googleAuth.'.$error], $status)
            ->header('Cache-Control', 'no-store, private');
    }

    private function redirectResult(string $lang, array $query, bool $mobile)
    {
        $base = $mobile ? (string) config('services.google.mobile_app_link') : (string) config('app.frontend_url');
        if (! $this->safeConfiguredUrl($base)) {
            return $this->jsonError('unavailable', 503);
        }
        $url = rtrim($base, '/').($mobile ? '' : "/{$lang}/auth/google-callback");

        return redirect()->away($url.'?'.http_build_query($mobile ? ['lang' => $lang] + $query : $query))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    private function resolveLang(Request $request): string
    {
        return $this->normalizeLang($request->query('lang') ?: app()->getLocale());
    }

    private function normalizeLang(mixed $lang): string
    {
        return is_string($lang) && in_array(strtolower($lang), ['ar', 'en', 'ru', 'fr', 'zh', 'es', 'de', 'it', 'hi', 'ja', 'fa', 'ur', 'tr'], true)
            ? strtolower($lang) : 'en';
    }
}
