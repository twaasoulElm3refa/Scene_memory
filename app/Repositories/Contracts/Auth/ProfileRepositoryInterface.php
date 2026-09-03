<?php

namespace App\Repositories\Contracts\Auth;

interface ProfileRepositoryInterface
{
    public function getProfileActivity(
        int $userId,
        int $page = 1,
        int $perPage = 20
    );

    public function clearUserProfileCache(int $userId): void;
}
