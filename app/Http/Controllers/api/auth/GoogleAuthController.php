<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function googleLogin()
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return response()->json([
            'status' => 'success',
            'url' => $url,
        ]);
    }

    public function googleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'licence_type_id'=>1,
                ]
            );
            if($user)
                {
                    $user->update([
                        'last_login_at' => now(),
                    ]);
                }
            $token = $user->createToken('google-auth-token')->plainTextToken;

            $isProfileComplete =
                !empty($user->phone) &&
                !empty($user->country) &&
                !empty($user->position) &&
                !empty($user->date_of_birth);
            Mail::to($user->email)->queue(new WelcomeMail($user));
            return response()->json([
                'status' => 'success',
                'token' => $token,
                'role' => $user->role,
                'is_profile_complete' => $isProfileComplete,
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
