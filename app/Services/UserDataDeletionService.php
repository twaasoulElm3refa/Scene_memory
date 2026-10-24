<?php

namespace App\Services;

use App\Mail\DataDeletionConfirmationMail;
use App\Models\UserDataDeletionRequest;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserDataDeletionService
{
    public const MAX_CODE_ATTEMPTS = 5;
    public const CODE_EXPIRY_MINUTES = 15;

    public function __construct(
        private readonly GoogleAccountService $googleAccountService
    ) {}

    public function getActiveRequestForUser(User $user): ?UserDataDeletionRequest
    {
        return UserDataDeletionRequest::where('user_id', $user->id)
            ->whereIn('status', [
                UserDataDeletionRequest::STATUS_PENDING_CONFIRMATION,
                UserDataDeletionRequest::STATUS_PENDING,
                UserDataDeletionRequest::STATUS_UNDER_REVIEW,
                UserDataDeletionRequest::STATUS_PROCESSING,
            ])
            ->latest('id')
            ->first();
    }

    public function checkFinancialObligations(User $user): array
    {
        // 1. Check wallet balance
        if ($user->wallet) {
            $minor = (int) ($user->wallet->balance_minor ?? 0);
            $amount = (float) ($user->wallet->amount ?? 0);
            if ($minor > 0 || $amount > 0) {
                return [
                    'has_obligations' => true,
                    'reason' => 'Active wallet balance requires settlement or forfeiture prior to account deletion.',
                ];
            }
        }

        // 2. Check pending withdrawals
        $hasPendingWithdrawals = Withdraw::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($hasPendingWithdrawals) {
            return [
                'has_obligations' => true,
                'reason' => 'Pending withdrawal requests are currently being processed.',
            ];
        }

        return [
            'has_obligations' => false,
            'reason' => null,
        ];
    }

    public function createRequest(
        User $user,
        string $type = UserDataDeletionRequest::TYPE_FULL_ACCOUNT,
        ?string $reason = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): UserDataDeletionRequest {
        $existing = $this->getActiveRequestForUser($user);
        if ($existing) {
            return $existing;
        }

        $hasPassword = $this->googleAccountService->hasUsablePasswordCredential($user);
        $requiresCode = ! $hasPassword;

        $code = null;
        $codeHash = null;
        $expiresAt = null;

        if ($requiresCode) {
            $code = (string) random_int(100000, 999999);
            $codeHash = Hash::make($code);
            $expiresAt = now()->addMinutes(self::CODE_EXPIRY_MINUTES);
        }

        $reference = $this->generateUniqueReference();
        $financial = $this->checkFinancialObligations($user);

        $request = UserDataDeletionRequest::create([
            'reference' => $reference,
            'user_id' => $user->id,
            'user_identifier' => hash('sha256', (string) $user->id),
            'masked_email' => $this->maskEmail($user->email ?? ''),
            'request_type' => $type,
            'status' => UserDataDeletionRequest::STATUS_PENDING_CONFIRMATION,
            'reason' => $reason,
            'requires_code' => $requiresCode,
            'confirmation_code_hash' => $codeHash,
            'confirmation_code_expires_at' => $expiresAt,
            'confirmation_code_attempts' => 0,
            'financial_obligations_detected' => $financial['has_obligations'],
            'financial_hold_reason' => $financial['reason'],
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 500) : null,
        ]);

        if ($requiresCode && ! empty($user->email) && $code) {
            $this->sendConfirmationMail($user->email, $code, $reference);
        }

        return $request;
    }

    public function resendCode(UserDataDeletionRequest $request, User $user): void
    {
        if ((int) $request->user_id !== (int) $user->id) {
            throw new AuthorizationException('Unauthorized deletion request.');
        }

        if (! $request->isPendingConfirmation()) {
            throw new \InvalidArgumentException('Confirmation code can only be resent for pending confirmation requests.');
        }

        $code = (string) random_int(100000, 999999);
        $request->update([
            'confirmation_code_hash' => Hash::make($code),
            'confirmation_code_expires_at' => now()->addMinutes(self::CODE_EXPIRY_MINUTES),
            'confirmation_code_attempts' => 0,
            'requires_code' => true,
        ]);

        if (! empty($user->email)) {
            $this->sendConfirmationMail($user->email, $code, $request->reference);
        }
    }

    public function confirmRequest(
        UserDataDeletionRequest $request,
        User $user,
        ?string $password = null,
        ?string $code = null
    ): UserDataDeletionRequest {
        if ((int) $request->user_id !== (int) $user->id) {
            throw new AuthorizationException('Unauthorized deletion request.');
        }

        if (! $request->isPendingConfirmation()) {
            throw new \InvalidArgumentException('Deletion request cannot be confirmed in current status: ' . $request->status);
        }

        if ($request->requires_code) {
            if (! is_string($code) || trim($code) === '') {
                throw ValidationException::withMessages([
                    'code' => ['The 6-digit confirmation code is required.'],
                ]);
            }

            if ($request->confirmation_code_expires_at && $request->confirmation_code_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'code' => ['The confirmation code has expired. Please request a new code.'],
                ]);
            }

            if ($request->confirmation_code_attempts >= self::MAX_CODE_ATTEMPTS) {
                throw ValidationException::withMessages([
                    'code' => ['Too many invalid confirmation attempts. Please contact support.'],
                ]);
            }

            if (! Hash::check($code, $request->confirmation_code_hash)) {
                $request->increment('confirmation_code_attempts');
                throw ValidationException::withMessages([
                    'code' => ['The confirmation code is invalid.'],
                ]);
            }
        } else {
            if (! is_string($password) || $password === '') {
                throw ValidationException::withMessages([
                    'password' => ['Your current password is required to confirm account deletion.'],
                ]);
            }

            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The provided password does not match our records.'],
                ]);
            }
        }

        $now = now();
        $financial = $this->checkFinancialObligations($user);

        if ($financial['has_obligations']) {
            $request->update([
                'status' => UserDataDeletionRequest::STATUS_UNDER_REVIEW,
                'financial_obligations_detected' => true,
                'financial_hold_reason' => $financial['reason'],
                'confirmed_at' => $now,
            ]);

            return $request->fresh();
        }

        $request->update([
            'status' => UserDataDeletionRequest::STATUS_PENDING,
            'confirmed_at' => $now,
        ]);

        return $this->executeDeletion($request);
    }

    public function cancelRequest(UserDataDeletionRequest $request, User $user): UserDataDeletionRequest
    {
        if ((int) $request->user_id !== (int) $user->id) {
            throw new AuthorizationException('Unauthorized deletion request.');
        }

        if (! $request->canBeCancelled()) {
            throw new \InvalidArgumentException('Deletion request cannot be cancelled in status: ' . $request->status);
        }

        $request->update([
            'status' => UserDataDeletionRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $request->fresh();
    }

    public function executeDeletion(UserDataDeletionRequest $request): UserDataDeletionRequest
    {
        if ($request->isCompleted() || $request->status === UserDataDeletionRequest::STATUS_CANCELLED) {
            return $request;
        }

        $request->update([
            'status' => UserDataDeletionRequest::STATUS_PROCESSING,
            'processed_at' => now(),
        ]);

        return DB::transaction(function () use ($request) {
            /** @var User|null $user */
            $user = User::lockForUpdate()->find($request->user_id);

            if (! $user) {
                $request->update([
                    'status' => UserDataDeletionRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                return $request->fresh();
            }

            // Check if obligations appeared between confirm and process
            $financial = $this->checkFinancialObligations($user);
            if ($financial['has_obligations'] && ! $request->isUnderReview()) {
                $request->update([
                    'status' => UserDataDeletionRequest::STATUS_UNDER_REVIEW,
                    'financial_obligations_detected' => true,
                    'financial_hold_reason' => $financial['reason'],
                ]);

                return $request->fresh();
            }

            // 1. Remove profile photo
            if ($user->image) {
                try {
                    if (Storage::disk('public')->exists($user->image)) {
                        Storage::disk('public')->delete($user->image);
                    }
                } catch (Throwable $e) {
                    Log::warning('Failed deleting user avatar during data deletion', [
                        'user_id' => $user->id,
                        'image' => $user->image,
                    ]);
                }
            }

            // 2. Remove transient personal data
            try {
                $user->cart()->delete();
            } catch (Throwable) {}

            try {
                $user->wishlists()->delete();
            } catch (Throwable) {}

            try {
                $user->registrationOtps()->delete();
            } catch (Throwable) {}

            if (Schema::hasTable('notifications')) {
                try {
                    DB::table('notifications')
                        ->where('notifiable_type', User::class)
                        ->where('notifiable_id', $user->id)
                        ->delete();
                } catch (Throwable) {}
            }

            // 3. Deactivate events so they are removed from marketplace/search
            try {
                $user->events()->update(['is_active' => false]);
            } catch (Throwable) {}

            $retentionPolicy = [
                'tax_and_accounting' => 'Financial transactions, withdrawal history, and wallet ledgers retained per statutory financial obligations.',
                'purchased_entitlements' => 'Entitlement access preserved for third-party media purchasers.',
                'anonymized_at' => now()->toIso8601String(),
                'anonymized_user_id' => $user->id,
            ];

            // 4. Anonymize user PII and scramble credentials
            $anonymizedName = 'Deleted User #' . $user->id;
            $anonymizedEmail = 'deleted-' . $user->id . '-' . Str::lower(Str::random(10)) . '@deleted.scemory.local';

            $user->forceFill([
                'name' => $anonymizedName,
                'email' => $anonymizedEmail,
                'phone' => null,
                'country' => null,
                'position' => null,
                'date_of_birth' => null,
                'image' => null,
                'google_id' => null,
                'google_avatar_url' => null,
                'facebook_id' => null,
                'facebook_avatar_url' => null,
                'password' => Hash::make(Str::random(64)),
                'slug' => null,
                'is_active' => false,
                'deleted_at' => now(),
            ])->save();

            // 5. Revoke authentication tokens & sessions
            try {
                $user->tokens()->delete();
            } catch (Throwable) {}

            if (Schema::hasTable('sessions')) {
                try {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                } catch (Throwable) {}
            }

            try {
                Cache::tags(['user_profile', 'user_' . $user->id])->flush();
            } catch (Throwable) {}

            Cache::forget('my_events_user_id_' . $user->id);

            // 6. Complete request
            $request->update([
                'status' => UserDataDeletionRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'retention_policy_applied' => $retentionPolicy,
            ]);

            return $request->fresh();
        });
    }

    public function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }

        [$name, $domain] = explode('@', $email, 2);
        $len = mb_strlen($name);
        if ($len <= 2) {
            $maskedName = mb_substr($name, 0, 1) . '*';
        } else {
            $maskedName = mb_substr($name, 0, 1) . str_repeat('*', max(1, $len - 2)) . mb_substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    private function generateUniqueReference(): string
    {
        do {
            $ref = 'DEL-' . strtoupper(Str::random(6)) . '-' . strtoupper(Str::random(4));
        } while (UserDataDeletionRequest::where('reference', $ref)->exists());

        return $ref;
    }

    private function sendConfirmationMail(string $email, string $code, string $reference): void
    {
        try {
            Mail::to($email)->send(new DataDeletionConfirmationMail($code, $reference));
        } catch (Throwable $e) {
            Log::warning('Data deletion confirmation email failed', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
