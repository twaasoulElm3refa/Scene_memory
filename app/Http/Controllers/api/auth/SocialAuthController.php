<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToFacebook()
    {
        $url = Socialite::driver('facebook')->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function handleFacebookCallback()
    {
        try {
            $fbUser = Socialite::driver('facebook')->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $fbUser->getEmail()],
                [
                    'name' => $fbUser->getName() ?? 'Facebook User',
                    'password' => bcrypt(Str::random(16)),
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            $role = $user->role ?? 'user';

            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return redirect()->to($frontendUrl."?token=$token&role=$role");

        } catch (\Exception $e) {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return redirect()->to($frontendUrl.'?error='.urlencode($e->getMessage()));
        }
    }
}
