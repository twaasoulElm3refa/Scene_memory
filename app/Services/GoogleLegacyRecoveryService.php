<?php

namespace App\Services;

use App\Exceptions\GoogleAuthException;
use App\Mail\GoogleRecoveryCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GoogleLegacyRecoveryService
{
    public const EXPIRES_SECONDS = 600;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly GoogleAccountService $accounts) {}

    public function start(array $identity): array
    {
        $user = $this->recoverableUser($identity);
        $cooldownKey = 'google-recovery-send:'.hash('sha256', (string) $user->getKey());
        if (! Cache::add($cooldownKey, true, self::RESEND_COOLDOWN_SECONDS)) {
            throw new GoogleAuthException('recovery_rate_limited', 429);
        }

        $code = (string) random_int(100000, 999999);
        try {
            if ($this->mailerMayLogMessages()) {
                throw new GoogleAuthException('recovery_unavailable', 503);
            }
            Mail::to($user->email)->send(new GoogleRecoveryCodeMail($code));
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Google legacy recovery email could not be sent.', [
                'user_id' => $user->getKey(),
                'exception' => $e::class,
            ]);
            throw new GoogleAuthException('recovery_unavailable', 503);
        }

        return [
            'user_id' => $user->getKey(),
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::EXPIRES_SECONDS)->timestamp,
            'masked_email' => $this->maskEmail($user->email),
        ];
    }

    public function complete(array $identity, int $userId): User
    {
        $user = $this->recoverableUser($identity, $userId);
        $this->accounts->linkAccount($user, $identity);

        return $user;
    }

    private function recoverableUser(array $identity, ?int $expectedUserId = null): User
    {
        $user = User::whereRaw('LOWER(email) = ?', [$identity['email'] ?? ''])->first();
        if (! $user || ($expectedUserId !== null && $user->getKey() !== $expectedUserId)) {
            throw new GoogleAuthException('recovery_expired', 422);
        }
        if (! $user->is_active || $user->deleted_at !== null) {
            throw new GoogleAuthException('disabled', 403);
        }
        if ($user->google_id !== null || $user->facebook_id !== null
            || $this->accounts->hasUsablePasswordCredential($user)) {
            throw new GoogleAuthException('recovery_expired', 422);
        }

        return $user;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).str_repeat('*', max(1, mb_strlen($name) - 1)).($domain ? "@{$domain}" : '');
    }

    private function mailerMayLogMessages(): bool
    {
        $default = (string) config('mail.default');
        $mailer = config("mail.mailers.{$default}", []);
        $transport = $mailer['transport'] ?? null;

        if ($transport === 'log') {
            return true;
        }

        return in_array($transport, ['failover', 'roundrobin'], true)
            && in_array('log', $mailer['mailers'] ?? [], true);
    }
}
