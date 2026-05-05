<?php

namespace App\Repositories\Eloquent\Auth;

use App\Models\User;
use App\Models\Wallet;
use App\Models\cart;
use App\Models\cartItems;
use App\Repositories\Contracts\Auth\AuthRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AuthRepository implements AuthRepositoryInterface
{
    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function updateUserLastLogin(User $user): void
    {
        $user->update(['last_login_at' => now()]);
    }

    public function findOrCreateCartByUserId(int $userId): cart
    {
        return cart::firstOrCreate(['user_id' => $userId]);
    }

    public function countCartItems(int $cartId): int
    {
        return cartItems::where('cart_id', $cartId)->count();
    }

    public function createWalletIfMissing(int $userId): void
    {
        if (! Wallet::where('user_id', $userId)->exists()) {
            Wallet::create(['user_id' => $userId, 'amount' => 0]);
        }
    }

    public function upsertPasswordResetToken(string $email, string $hashedToken): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => $hashedToken, 'created_at' => now()]
        );
    }

    public function getPasswordResetToken(string $email): ?object
    {
        return DB::table('password_reset_tokens')->where('email', $email)->first();
    }

    public function updateUserPasswordByEmail(string $email, string $hashedPassword): void
    {
        User::where('email', $email)->update(['password' => $hashedPassword]);
    }

    public function deletePasswordResetToken(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
