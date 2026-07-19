<?php

namespace App\Services;

use App\Exceptions\RegistrationOtpException;
use App\Mail\RegisterOtpMail;
use App\Models\RegistrationOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RegistrationOtpService
{
    public const EXPIRES_SECONDS = 600;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public function createForUser(User $user): array
    {
        $otp = (string) random_int(100000, 999999);

        $record = DB::transaction(function () use ($user, $otp) {
            $this->invalidateActiveOtps($user);

            return RegistrationOtp::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(self::EXPIRES_SECONDS),
            ]);
        });

        return [
            'record' => $record,
            'otp' => $otp,
        ];
    }

    public function send(User $user, string $otp): void
    {
        if ($this->mailerMayLogMessages()) {
            Log::error('Registration OTP email mailer is unsafe for OTP delivery', [
                'user_id' => $user->id,
                'email' => $this->maskEmail($user->email),
                'mailer' => config('mail.default'),
            ]);

            throw new RegistrationOtpException('Unable to send verification email.', 500);
        }

        try {
            Mail::to($user->email)->send(new RegisterOtpMail($otp));
        } catch (Throwable $e) {
            Log::error('Registration OTP email failed', [
                'user_id' => $user->id,
                'email' => $this->maskEmail($user->email),
                'message' => $e->getMessage(),
            ]);

            throw new RegistrationOtpException('Unable to send verification email.', 500);
        }
    }

    public function verify(User $user, string $otp): User
    {
        if ($this->isVerified($user)) {
            throw new RegistrationOtpException('This account is already verified.', 422, 'email');
        }

        $result = DB::transaction(function () use ($user, $otp) {
            /** @var RegistrationOtp|null $record */
            $record = RegistrationOtp::where('user_id', $user->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $record || $record->verified_at) {
                return new RegistrationOtpException('Invalid verification code.', 422, 'otp');
            }

            if ($record->expires_at->isPast()) {
                return new RegistrationOtpException('Verification code has expired.', 422, 'otp');
            }

            if ($record->attempts >= self::MAX_ATTEMPTS) {
                return new RegistrationOtpException('Too many verification attempts.', 429, 'otp');
            }

            if (! Hash::check($otp, $record->otp_hash)) {
                $record->increment('attempts');

                if ($record->refresh()->attempts >= self::MAX_ATTEMPTS) {
                    return new RegistrationOtpException('Too many verification attempts.', 429, 'otp');
                }

                return new RegistrationOtpException('Invalid verification code.', 422, 'otp');
            }

            $now = now();
            $record->update(['verified_at' => $now]);

            RegistrationOtp::where('user_id', $user->id)
                ->whereNull('verified_at')
                ->update(['verified_at' => $now]);

            $user->forceFill([
                'is_active' => true,
                'email_verified_at' => $now,
            ])->save();

            return $user->refresh();
        });

        if ($result instanceof RegistrationOtpException) {
            throw $result;
        }

        return $result;
    }

    public function ensureCanResend(User $user): void
    {
        if ($this->isVerified($user)) {
            throw new RegistrationOtpException('This account is already verified.', 422, 'email');
        }

        $latest = RegistrationOtp::where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($latest && $latest->created_at->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            throw new RegistrationOtpException('Please wait before requesting another code.', 429, 'email');
        }
    }

    public function isVerified(User $user): bool
    {
        return $user->email_verified_at !== null;
    }

    public function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $maskedName = mb_substr($name, 0, 1).str_repeat('*', max(1, mb_strlen($name) - 1));

        return $domain ? "{$maskedName}@{$domain}" : $maskedName;
    }

    private function invalidateActiveOtps(User $user): void
    {
        RegistrationOtp::where('user_id', $user->id)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);
    }

    private function mailerMayLogMessages(): bool
    {
        $default = (string) config('mail.default');
        $mailer = config("mail.mailers.{$default}", []);
        $transport = $mailer['transport'] ?? null;

        if ($transport === 'log') {
            return true;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            return in_array('log', $mailer['mailers'] ?? [], true);
        }

        return false;
    }
}
