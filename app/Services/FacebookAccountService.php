<?php

namespace App\Services;

use App\Exceptions\FacebookAuthException;
use App\Models\LicenceType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as ProviderUser;

class FacebookAccountService
{
    public function verifiedIdentity(ProviderUser $facebook): array
    {
        // Socialite exchanges the code with the configured app credentials and
        // retrieves /me server-side, including its appsecret_proof protection.
        $id = $facebook->getId();
        if (! is_string($id) || ! preg_match('/\A[0-9]{1,255}\z/', $id)
            || ($facebook->getRaw()['id'] ?? null) !== $id) {
            throw new FacebookAuthException('identity_invalid');
        }
        $email = $facebook->getEmail();
        $avatar = $facebook->getAvatar();

        return [
            'id' => $id,
            'name' => Str::limit(trim((string) $facebook->getName()), 200, '') ?: 'Scemory',
            'email' => is_string($email) && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL)
                ? Str::lower($email) : null,
            'avatar' => is_string($avatar) && strlen($avatar) <= 2048
                && filter_var($avatar, FILTER_VALIDATE_URL) && parse_url($avatar, PHP_URL_SCHEME) === 'https'
                && ! parse_url($avatar, PHP_URL_USER) ? $avatar : null,
        ];
    }

    public function existingAccount(array $identity): ?User
    {
        $user = User::where('facebook_id', $identity['id'])->first();
        if ($user) {
            if (! $user->is_active || $user->deleted_at !== null) {
                throw new FacebookAuthException('disabled', 403);
            }

            return $user;
        }
        if ($identity['email'] !== null) {
            $this->assertEmailAvailable($identity['email']);
        }

        return null;
    }

    public function assertEmailAvailable(string $email): void
    {
        if (User::whereRaw('LOWER(email) = ?', [Str::lower($email)])->exists()) {
            throw new FacebookAuthException('linking_required', 409);
        }
    }

    // Identity comes only from the authenticated server-side provider response.
    public function registerIdentity(array $identity): User
    {
        return Cache::lock('facebook-account:'.hash('sha256', $identity['id']), 10)->block(3,
            fn () => Cache::lock('facebook-email:'.hash('sha256', $identity['email'] ?? 'subject:'.$identity['id']), 10)->block(3,
                function () use ($identity) {
                    if ($user = $this->existingAccount($identity)) {
                        return $user;
                    }
                    if ($identity['email'] !== null) {
                        $this->assertEmailAvailable($identity['email']);
                    }
                    try {
                        $user = DB::transaction(function () use ($identity) {
                            $name = $identity['name'];
                            if (User::where('name', $name)->exists()) {
                                $name .= ' '.Str::random(12);
                            }

                            return User::create([
                                'facebook_id' => $identity['id'], 'facebook_avatar_url' => $identity['avatar'],
                                'name' => $name, 'email' => $identity['email'], 'password' => null,
                                // Facebook authenticates its subject. It does not supply
                                // explicit email-verification evidence in this flow.
                                'role' => 'user', 'is_active' => true, 'email_verified_at' => null,
                                'licence_type_id' => LicenceType::whereKey(1)->exists() ? 1 : null,
                            ]);
                        });
                    } catch (UniqueConstraintViolationException) {
                        $user = $this->existingAccount($identity);
                        if (! $user) {
                            if ($identity['email'] !== null) {
                                $this->assertEmailAvailable($identity['email']);
                            }
                            throw new FacebookAuthException('callback_failed');
                        }
                    }

                    return $user;
                }
            )
        );
    }
}
