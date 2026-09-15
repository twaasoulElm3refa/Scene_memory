<?php

namespace App\Repositories\Contracts\Auth;

interface ProfileRepositoryInterface
{
    public function getProfileActivity(
        int $userId,
        array $filters = []
    ): array;

    public function clearUserProfileCache(int $userId): void;
}
