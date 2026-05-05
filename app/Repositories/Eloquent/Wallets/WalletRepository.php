<?php

namespace App\Repositories\Eloquent\Wallets;

use App\Models\Wallet;
use App\Repositories\Contracts\Wallets\WalletRepositoryInterface;

class WalletRepository implements WalletRepositoryInterface
{
    public function findByUserId(int $userId)
    {
        return Wallet::where('user_id', $userId)->first();
    }

    public function findByUserIdForUpdate(int $userId)
    {
        return Wallet::where('user_id', $userId)->lockForUpdate()->first();
    }
}
