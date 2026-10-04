<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Repositories\Contracts\Users\UserRepositoryInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private const MOBILE_STATE_PREFIX = 'scemory-mobile-v1.';

    private const MOBILE_STATE_TTL_MINUTES = 10;

    private const MOBILE_DELIVERY_TTL_MINUTES = 2;

    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function googleLogin(Request $request)
    {
        $lang = $this->resolveLang($request);
        $provider = Socialite::driver('google')->stateless();

        if ($request->boolean('mobile')) {
            $challenge = $request->validate([
                'code_challenge' => ['required', 'string', 'size:43', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            ])['code_challenge'];
            $provider->with(['state' => $this->createMobileOAuthState($lang, $challenge)]);
        }

        $url = $provider->redirect()->getTargetUrl();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'url' => $url,
            ]);
        }

        return redirect()->away($url)->withCookie(cookie('oauth_lang', $lang, 10, '/'));
    }

    public function googleCallback(Request $request)
    {
        $mobileAttempt = $this->consumeMobileOAuthState($request->query('state'));

        if ($mobileAttempt !== null && ! $mobileAttempt['valid']) {
            return $this->redirectToMobile($mobileAttempt['lang'], [
                'error' => $mobileAttempt['error'],
            ]);
        }

        if ($mobileAttempt !== null && $request->filled('error')) {
            $error = $request->query('error') === 'access_denied'
                ? 'cancelled'
                : 'oauth_failed';

            return $this->redirectToMobile($mobileAttempt['lang'], ['error' => $error]);
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = $this->userRepository->firstOrCreateByEmail(
                $googleUser->getEmail(),
                [
                    'name' => $googleUser->getName(),
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'licence_type_id' => 1,
                ]
            );
            if ($user) {
                $user->update([
                    'is_active' => true,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                    'last_login_at' => now(),
                ]);
            }
            $isProfileComplete =
                ! empty($user->phone) &&
                ! empty($user->country) &&
                ! empty($user->position) &&
                ! empty($user->date_of_birth);
            if ($user->wasRecentlyCreated) {
                Mail::to($user->email)->queue(new WelcomeMail($user));
            }

            if ($mobileAttempt !== null) {
                $code = $this->createMobileDeliveryCode(
                    $user->getKey(),
                    $mobileAttempt['code_challenge']
                );

                return $this->redirectToMobile($mobileAttempt['lang'], ['code' => $code]);
            }

            $token = $user->createToken('google-auth-token')->plainTextToken;

            $payload = [
                'status' => 'success',
                'token' => $token,
                'role' => $user->role,
                'is_profile_complete' => $isProfileComplete,
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ];

            if ($request->expectsJson()) {

                return response()->json($payload);
            }

            $lang = $this->resolveLang($request);
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            $query = http_build_query([
                'token' => $token,
                'role' => $user->role,
                'is_profile_complete' => $isProfileComplete ? 'true' : 'false',
            ]);

            return redirect()
                ->away("{$frontendUrl}/{$lang}/auth/google-callback?{$query}")
                ->withoutCookie('oauth_lang');

        } catch (Throwable $e) {
            if ($request->expectsJson()) {
                Log::error($e);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Something went wrong',
                ], 400);
            }

            if ($mobileAttempt !== null) {
                Log::warning('Google mobile OAuth callback failed.', [
                    'exception' => $e::class,
                ]);

                return $this->redirectToMobile($mobileAttempt['lang'], ['error' => 'callback_failed']);
            }

            $lang = $this->resolveLang($request);
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            $error = urlencode($e->getMessage());

            return redirect()
                ->away("{$frontendUrl}/{$lang}/auth/google-callback?error={$error}")
                ->withoutCookie('oauth_lang');
        }
    }

    public function exchangeMobileCode(Request $request)
    {
        $code = $request->validate([
            'code' => ['required', 'string', 'size:64', 'regex:/\A[A-Za-z0-9]+\z/'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128', 'regex:/\A[A-Za-z0-9._~-]+\z/'],
        ])['code'];
        $codeVerifier = (string) $request->input('code_verifier');
        $codeHash = hash('sha256', $code);

        try {
            $delivery = Cache::lock("google-mobile-delivery-lock:{$codeHash}", 10)
                ->block(3, fn () => Cache::pull("google-mobile-delivery:{$codeHash}"));
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Sign in is already being completed. Please try again.',
            ], 409);
        }

        if (! is_array($delivery) || ! isset($delivery['user_id'])) {
            return response()->json([
                'message' => 'Sign in link expired or was already used. Please try again.',
            ], 422);
        }

        $actualChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        if (! isset($delivery['code_challenge']) || ! hash_equals($delivery['code_challenge'], $actualChallenge)) {
            return response()->json([
                'message' => 'This sign in link does not belong to this app session.',
            ], 422);
        }

        $user = $this->userRepository->findById((int) $delivery['user_id']);

        if (! $user || ! $user->is_active) {
            return response()->json([
                'message' => 'This account is no longer available.',
            ], 422);
        }

        $isProfileComplete =
            ! empty($user->phone) &&
            ! empty($user->country) &&
            ! empty($user->position) &&
            ! empty($user->date_of_birth);

        $payload = [
            'status' => 'success',
            'token' => $user->createToken('google-mobile-auth-token')->plainTextToken,
            'role' => $user->role,
            'is_profile_complete' => $isProfileComplete,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];

        return response()->json($payload);
    }

    private function createMobileOAuthState(string $lang, string $codeChallenge): string
    {
        $nonce = Str::random(64);
        $expiresAt = now()->addMinutes(self::MOBILE_STATE_TTL_MINUTES);
        $nonceHash = hash('sha256', $nonce);

        Cache::put("google-mobile-state:{$nonceHash}", $nonceHash, $expiresAt);

        return self::MOBILE_STATE_PREFIX.Crypt::encryptString(json_encode([
            'channel' => 'android',
            'nonce' => $nonce,
            'lang' => $lang,
            'code_challenge' => $codeChallenge,
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    private function consumeMobileOAuthState(mixed $state): ?array
    {
        if (! is_string($state) || ! str_starts_with($state, self::MOBILE_STATE_PREFIX)) {
            return null;
        }

        $fallback = ['valid' => false, 'lang' => 'en', 'error' => 'invalid_state'];

        try {
            $payload = json_decode(
                Crypt::decryptString(substr($state, strlen(self::MOBILE_STATE_PREFIX))),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return $fallback;
        }

        if (
            ($payload['channel'] ?? null) !== 'android'
            || ! is_string($payload['nonce'] ?? null)
            || ! is_string($payload['code_challenge'] ?? null)
            || ! preg_match('/\A[A-Za-z0-9_-]{43}\z/', $payload['code_challenge'])
        ) {
            return $fallback;
        }

        $lang = $this->normalizeLang($payload['lang'] ?? 'en');
        $nonceHash = hash('sha256', $payload['nonce']);

        if (! is_int($payload['expires_at'] ?? null) || $payload['expires_at'] < now()->getTimestamp()) {
            Cache::forget("google-mobile-state:{$nonceHash}");

            return ['valid' => false, 'lang' => $lang, 'error' => 'expired_state'];
        }

        try {
            $storedState = Cache::lock("google-mobile-state-lock:{$nonceHash}", 10)
                ->block(3, fn () => Cache::pull("google-mobile-state:{$nonceHash}"));
        } catch (LockTimeoutException) {
            $storedState = null;
        }

        if (! is_string($storedState) || ! hash_equals($nonceHash, $storedState)) {
            return ['valid' => false, 'lang' => $lang, 'error' => 'invalid_state'];
        }

        return [
            'valid' => true,
            'lang' => $lang,
            'error' => null,
            'code_challenge' => $payload['code_challenge'],
        ];
    }

    private function createMobileDeliveryCode(int $userId, string $codeChallenge): string
    {
        $code = Str::random(64);

        Cache::put(
            'google-mobile-delivery:'.hash('sha256', $code),
            ['user_id' => $userId, 'code_challenge' => $codeChallenge],
            now()->addMinutes(self::MOBILE_DELIVERY_TTL_MINUTES)
        );

        return $code;
    }

    private function redirectToMobile(string $lang, array $query)
    {
        $url = rtrim((string) config('services.google.mobile_app_link'), '/?');

        return redirect()->away($url.'?'.http_build_query(['lang' => $lang] + $query))
            ->withoutCookie('oauth_lang');
    }

    private function resolveLang(Request $request): string
    {
        return $this->normalizeLang(
            $request->query('lang') ?: $request->cookie('oauth_lang') ?: app()->getLocale() ?: 'en'
        );
    }

    private function normalizeLang(mixed $lang): string
    {
        $lang = strtolower((string) $lang);
        $supported = ['ar', 'en', 'ru', 'fr', 'zh', 'es', 'de', 'it', 'hi', 'ja', 'fa', 'ur', 'tr'];

        return in_array($lang, $supported, true) ? $lang : 'en';
    }
}
