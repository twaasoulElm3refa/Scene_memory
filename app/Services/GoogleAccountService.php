<?php

namespace App\Services;

use App\Exceptions\GoogleAuthException;
use App\Mail\WelcomeMail;
use App\Models\LicenceType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as GoogleUser;
use Throwable;

class GoogleAccountService
{
    private const LEGACY_PLACEHOLDER_PASSWORDS = [
        'password',
        'legacy_google_oauth_user_scemory_default',
    ];

    public function verifiedIdentity(GoogleUser $google): array
    {
        // Socialite obtains this from Google's authenticated HTTPS userinfo endpoint,
        // using the access token exchanged server-side for the authorization code.
        $raw = $google->getRaw();
        $sub = $raw['sub'] ?? null;
        $email = $google->getEmail();
        if (! is_string($sub) || $sub === '' || strlen($sub) > 255
            || $google->getId() !== $sub
            || ($raw['email_verified'] ?? false) !== true
            || ! is_string($email) || strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GoogleAuthException('identity_invalid');
        }

        $avatar = $google->getAvatar();

        return [
            'sub' => $sub,
            'email' => Str::lower($email),
            'name' => Str::limit(trim((string) $google->getName()), 200, '') ?: 'Scemory',
            'avatar' => is_string($avatar) && strlen($avatar) <= 2048
                && filter_var($avatar, FILTER_VALIDATE_URL)
                && parse_url($avatar, PHP_URL_SCHEME) === 'https'
                && ! parse_url($avatar, PHP_URL_USER) ? $avatar : null,
        ];
    }

    public function hasUsablePasswordCredential(User $user): bool
    {
        if (! is_string($user->password) || $user->password === '') {
            return false;
        }

        foreach (self::LEGACY_PLACEHOLDER_PASSWORDS as $placeholder) {
            if (password_verify($placeholder, $user->password)) {
                return false;
            }
        }

        return true;
    }

    public function passwordProvesOwnership(User $user, string $password): bool
    {
        return $this->hasUsablePasswordCredential($user)
            && password_verify($password, $user->password);
    }

    public function hasHistoricalGoogleLink(User $user): bool
    {
        if ($user->google_id !== null) {
            return true;
        }

        if (! empty($user->google_avatar_url)) {
            return true;
        }

        if ($user->tokens()->whereIn('name', ['google-auth-token', 'google-mobile-auth-token'])->exists()) {
            return true;
        }

        return false;
    }

    public function existingAccount(array $identity): ?User
    {
        $user = User::where('google_id', $identity['sub'])->first();
        if ($user) {
            if (! $user->is_active || $user->deleted_at !== null) {
                throw new GoogleAuthException('disabled', 403);
            }

            return $user;
        }

        $candidate = User::whereRaw('LOWER(email) = ?', [$identity['email']])->first();
        if ($candidate) {
            if (! $candidate->is_active || $candidate->deleted_at !== null) {
                throw new GoogleAuthException('disabled', 403);
            }
            if ($candidate->google_id !== null) {
                throw new GoogleAuthException('identity_conflict', 409);
            }
            if ($this->hasUsablePasswordCredential($candidate)) {
                // Email alone is never sufficient evidence to take ownership of an existing account.
                throw new GoogleAuthException('linking_required', 409);
            }

            // Historical Google accounts created on or before October 4 have an established Google link
            // (e.g. personal access tokens named google-auth-token / google-mobile-auth-token or stored avatar)
            // and no usable password credential. Recognize, persist google_id, and sign in directly.
            if ($this->hasHistoricalGoogleLink($candidate)) {
                return $this->linkAccount($candidate, $identity);
            }

            if ($candidate->facebook_id !== null) {
                // An account already controlled through another provider must be linked
                // from an authenticated profile, never through an unusable password form.
                throw new GoogleAuthException('account_login_required', 409);
            }

            // Old Google registrations used a shared placeholder password and did not
            // persist Google's subject. They require one independent, single-use proof
            // sent to the address already registered on the account.
            throw new GoogleAuthException('recovery_required', 409);
        }

        return null;
    }

    public function linkAccount(User $user, array $identity): User
    {
        if (! $user->is_active || $user->deleted_at !== null) {
            throw new GoogleAuthException('disabled', 403);
        }

        $conflict = User::where('google_id', $identity['sub'])->where('id', '!=', $user->id)->first();
        if ($conflict) {
            throw new GoogleAuthException('identity_conflict', 409);
        }

        $user->forceFill([
            'google_id' => $identity['sub'],
            'google_avatar_url' => $user->google_avatar_url ?: $identity['avatar'],
        ])->save();

        return $user;
    }

    public function complete(array $identity): User
    {
        $user = Cache::lock('google-account:'.hash('sha256', $identity['sub']), 10)->block(3,
            fn () => Cache::lock('google-email:'.hash('sha256', $identity['email']), 10)->block(3,
                function () use ($identity) {
                    if ($user = $this->existingAccount($identity)) {
                        return $user;
                    }
                    try {
                        return DB::transaction(function () use ($identity) {
                            $name = $identity['name'];
                            if (User::where('name', $name)->exists()) {
                                $name .= ' '.Str::random(12);
                            }

                            return User::create([
                                'google_id' => $identity['sub'],
                                'google_avatar_url' => $identity['avatar'],
                                'name' => $name,
                                'email' => $identity['email'],
                                'password' => null,
                                'role' => 'user',
                                'is_active' => true,
                                'email_verified_at' => now(),
                                'licence_type_id' => LicenceType::whereKey(1)->exists() ? 1 : null,
                            ]);
                        });
                    } catch (UniqueConstraintViolationException) {
                        // A simultaneous password registration may have claimed the email.
                        return $this->existingAccount($identity) ?? throw new GoogleAuthException('callback_failed');
                    }
                }
            )
        );

        $user->forceFill(['last_login_at' => now()])->save();
        if ($user->wasRecentlyCreated) {
            try {
                Mail::to($user->email)->queue(new WelcomeMail($user));
            } catch (Throwable $e) {
                Log::warning('Google welcome email could not be queued.', ['exception' => $e::class]);
            }
        }

        return $user;
    }
}
