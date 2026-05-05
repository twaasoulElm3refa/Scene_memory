<?php

namespace App\Repositories\Contracts\Wallets;

interface WalletRepositoryInterface
{
    public function findByUserId(int $userId);
    public function findByUserIdForUpdate(int $userId);
}
