<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\authApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\loginRequest;
use App\Http\Requests\registerRequest;
use App\Http\Resources\userResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    use authApiResponse;

    public function register(registerRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $data = $request->validated();

                return User::create([
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'password' => Hash::make($data['password']),
                    'image' => $data['image'] ?? null,
                    'role' => 'user',
                    'is_active' => true,
                    'country' => $data['country'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'position' => $data['position'] ?? null,
                    'last_login_at' => now(),
                ]);
            });
            $token = $user->createToken('rag-token')->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => new UserResource($user),
            ], 'Registered successfully.');

        } catch (Throwable $e) {
            \Log::error('Register Error', [
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'Registration failed. Please try again later.',
                500
            );
        }
    }

    public function login(loginRequest $request)
    {
        $request->validated();

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->unauthorized('Invalid credentials.');
        }

        if (! $user->is_active) {
            return $this->forbidden('Account is disabled.');
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('rag-token')->plainTextToken;

        return $this->success([
            'user' => new userResource($user),
            'token' => $token,
        ], 'Logged in successfully.');
    }

    public function profile()
    {
        $user = auth()->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }
        $cacheKey = 'user_profile_'.$user->id;
        $cachedProfile = Cache::remember($cacheKey, 60, function () use ($user) {
            return new UserResource($user);
        });

        return $this->success(['user' => $cachedProfile], 'Profile fetched successfully.');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'لو الإيميل موجود هيوصلك كود',
            ]);
        }

        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        Mail::raw("كود استرجاع كلمة المرور هو: $otp", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Reset Password OTP');
        });

        return response()->json([
            'message' => 'تم إرسال كود التحقق على الإيميل',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->otp, $record->token)) {
            return response()->json([
                'message' => 'الكود غير صحيح',
            ], 400);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'confirm_password' => 'required|string|same:new_password',
        ]);

        // Check current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect.',
            ], 403);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password updated successfully.',
        ]);
    }
}
