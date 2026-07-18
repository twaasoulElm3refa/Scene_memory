<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Repositories\Contracts\Users\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function googleLogin(Request $request)
    {
        $lang = $this->resolveLang($request);
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

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
            $token = $user->createToken('google-auth-token')->plainTextToken;

            $isProfileComplete =
                ! empty($user->phone) &&
                ! empty($user->country) &&
                ! empty($user->position) &&
                ! empty($user->date_of_birth);
            Mail::to($user->email)->queue(new WelcomeMail($user));

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

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 400);
            }

            $lang = $this->resolveLang($request);
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            $error = urlencode($e->getMessage());

            return redirect()
                ->away("{$frontendUrl}/{$lang}/auth/google-callback?error={$error}")
                ->withoutCookie('oauth_lang');
        }
    }

    private function resolveLang(Request $request): string
    {
        $lang = strtolower((string) ($request->query('lang') ?: $request->cookie('oauth_lang') ?: app()->getLocale() ?: 'en'));
        $supported = ['ar', 'en', 'ru', 'fr', 'zh', 'es', 'de', 'it', 'hi', 'ja', 'fa', 'ur', 'tr'];

        return in_array($lang, $supported, true) ? $lang : 'en';
    }
}
