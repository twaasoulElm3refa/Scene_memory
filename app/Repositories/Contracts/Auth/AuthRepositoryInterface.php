<?php

namespace App\Repositories\Contracts\Auth;

use App\Models\User;
use App\Models\Cart;

interface AuthRepositoryInterface
{
    public function createUser(array $data): User;
    public function findUserByEmail(string $email): ?User;
    public function updateUserLastLogin(User $user): void;
    public function findOrCreateCartByUserId(int $userId): Cart;
    public function countCartItems(int $cartId): int;
    public function createWalletIfMissing(int $userId): void;
    public function upsertPasswordResetToken(string $email, string $hashedToken): void;
    public function getPasswordResetToken(string $email): ?object;
    public function updateUserPasswordByEmail(string $email, string $hashedPassword): void;
    public function deletePasswordResetToken(string $email): void;
}
