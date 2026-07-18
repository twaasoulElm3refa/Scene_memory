<?php

namespace App\Http\Controllers\api\auth;

use App\Exceptions\RegistrationOtpException;
use App\Http\Controllers\concerns\authApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\loginRequest;
use App\Http\Requests\registerRequest;
use App\Http\Resources\userResource;
use App\Models\Events;
use App\Models\LicenceType;
use App\Repositories\Contracts\Auth\AuthRepositoryInterface;
use App\Services\RegistrationOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    use authApiResponse;

    public function __construct(
        private readonly AuthRepositoryInterface $authRepository,
        private readonly RegistrationOtpService $registrationOtpService
    ) {}

    public function register(registerRequest $request)
    {
        try {
            [$user, $otp] = DB::transaction(function () use ($request) {
                $data = $request->validated();
                $existingUser = $this->authRepository->findUserByEmail($data['email']);

                if ($existingUser && $this->registrationOtpService->isVerified($existingUser)) {
                    throw ValidationException::withMessages([
                        'email' => ['هذا البريد الإلكتروني مستخدم بالفعل.'],
                    ]);
                }

                $attributes = [
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'password' => Hash::make($data['password']),
                    'image' => $data['image'] ?? null,
                    'role' => 'user',
                    'is_active' => false,
                    'email_verified_at' => null,
                    'country' => $data['country'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'position' => $data['position'] ?? null,
                    'last_login_at' => null,
                    'licence_type_id' => $this->defaultLicenceTypeId(),
                ];

                if ($existingUser) {
                    $existingUser->forceFill($attributes)->save();
                    $user = $existingUser->refresh();
                } else {
                    $user = $this->authRepository->createUser($attributes);
                }

                $otpPayload = $this->registrationOtpService->createForUser($user);

                return [$user, $otpPayload['otp']];
            });

            $this->registrationOtpService->send($user, $otp);

            Cache::forget('users_all_page_'.request('page', 1));

            return response()->json([
                'status' => 'otp_required',
                'message' => 'Verification code sent successfully.',
                'data' => [
                    'email' => $this->registrationOtpService->maskEmail($user->email),
                    'expires_in' => RegistrationOtpService::EXPIRES_SECONDS,
                ],
            ], 202);

        } catch (ValidationException $e) {
            throw $e;
        } catch (RegistrationOtpException $e) {
            return $this->error($e->getMessage(), $e->statusCode(), $e->errors());

        } catch (Throwable $e) {
            Log::error('Register Error', [
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

        $user = $this->authRepository->findUserByEmail($request->email);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->unauthorized('Invalid credentials.');
        }

        if (! $user->is_active && ! $user->email_verified_at) {
            return $this->forbidden('Please verify your email first.');
        }

        if (! $user->is_active) {
            return $this->forbidden('Account is disabled.');
        }

        $this->authRepository->updateUserLastLogin($user);

        $token = $user->createToken('rag-token')->plainTextToken;

        return $this->success([
            'user' => new userResource($user),
            'token' => $token,
        ], 'Logged in successfully.');
    }

    public function verifyRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->authRepository->findUserByEmail($validated['email']);

        if (! $user) {
            return $this->validationError([
                'email' => ['Invalid verification code.'],
            ], 'Invalid verification code.');
        }

        try {
            $user = $this->registrationOtpService->verify($user, $validated['otp']);
        } catch (RegistrationOtpException $e) {
            return $this->error($e->getMessage(), $e->statusCode(), $e->errors());
        }

        $token = $user->createToken('rag-token')->plainTextToken;

        return $this->success([
            'user' => new userResource($user),
            'token' => $token,
        ], 'Email verified successfully.');
    }

    public function resendRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $this->authRepository->findUserByEmail($validated['email']);

        if (! $user) {
            return response()->json([
                'status' => 'otp_resent',
                'message' => 'A new verification code has been sent.',
                'data' => [
                    'expires_in' => RegistrationOtpService::EXPIRES_SECONDS,
                    'resend_after' => RegistrationOtpService::RESEND_COOLDOWN_SECONDS,
                ],
            ]);
        }

        try {
            $this->registrationOtpService->ensureCanResend($user);
            $otpPayload = $this->registrationOtpService->createForUser($user);
            $this->registrationOtpService->send($user, $otpPayload['otp']);
        } catch (RegistrationOtpException $e) {
            return $this->error($e->getMessage(), $e->statusCode(), $e->errors());
        }

        return response()->json([
            'status' => 'otp_resent',
            'message' => 'A new verification code has been sent.',
            'data' => [
                'expires_in' => RegistrationOtpService::EXPIRES_SECONDS,
                'resend_after' => RegistrationOtpService::RESEND_COOLDOWN_SECONDS,
            ],
        ]);
    }

    public function profile()
    {
        $user = auth()->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }

        $cart = $this->authRepository->findOrCreateCartByUserId($user->id);

        $cacheKey = 'user_profile_'.$user->id;
        $wallet = $user->wallet;
        if ($wallet == null) {
            $this->authRepository->createWalletIfMissing($user->id);
        }
        $items = $this->authRepository->countCartItems($cart->id);
        $EventCount = Events::where('user_id', $user->id)->count();
        $cachedProfile = Cache::tags(['user_profile', 'user_'.$user->id])
            ->remember($cacheKey, 60, function () use ($user, $items, $EventCount) {
                $user->load('licenceType');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position,
                    'country' => $user->country,
                    'date_of_birth' => $user->date_of_birth,
                    'phone' => $user->phone,
                    'event_count' => $EventCount,
                    'role' => $user->role,
                    'items' => $items,
                    'points' => $user->points,
                    'last_login_at' => $user->last_login_at,
                    'wallet' => $user->wallet,
                    'licenceType' => [
                        'id' => $user->licenceType?->id,
                        'name' => $user->licenceType?->name,
                        'price' => $user->licenceType?->price,
                        'is_active' => $user->licenceType?->is_active,
                        'created_at' => $user->licenceType?->created_at,
                    ],
                ];
            });

        return $this->success([
            'user' => $cachedProfile,
        ], 'Profile fetched successfully.');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = $this->authRepository->findUserByEmail($request->email);

        if (! $user) {
            return response()->json([
                'message' => 'لو الإيميل موجود هيوصلك كود',
            ]);
        }

        $otp = rand(100000, 999999);

        $this->authRepository->upsertPasswordResetToken($user->email, Hash::make($otp));

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

        $record = $this->authRepository->getPasswordResetToken($request->email);

        if (! $record || ! Hash::check($request->otp, $record->token)) {
            return response()->json([
                'message' => 'الكود غير صحيح',
            ], 400);
        }

        $this->authRepository->updateUserPasswordByEmail($request->email, Hash::make($request->password));

        $this->authRepository->deletePasswordResetToken($request->email);

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'position' => 'required|string',
            'date_of_birth' => 'required|string',
        ]);

        $user->update($validated);

        Cache::tags(['user_profile', 'user_'.$user->id])->flush();

        return $this->success($user, 'User updated Successfully');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8)->numbers()],
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

    public function wallet()
    {
        return $this->success(auth()->user()->wallet, 'Wallet fetched successfully.');
    }

    private function defaultLicenceTypeId(): ?int
    {
        return LicenceType::query()->whereKey(1)->exists() ? 1 : null;
    }
}
