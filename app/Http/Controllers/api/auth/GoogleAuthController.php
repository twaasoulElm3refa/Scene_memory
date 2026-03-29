<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'role' => $user->role,
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
