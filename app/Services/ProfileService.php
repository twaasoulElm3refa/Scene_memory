<?php

namespace App\Services;

use App\Repositories\Contracts\Auth\ProfileRepositoryInterface;

class ProfileService
{
    public function __construct(
        protected ProfileRepositoryInterface $repository,
        protected WeeklyLeaderboardService $weeklyLeaderboardService
    ) {}

    public function activity(
        int $userId,
        array $filters = []
    ): array {
        return [
            ...$this->repository->getProfileActivity($userId, $filters),
            'weekly_statistics' => $this->weeklyLeaderboardService->currentUserStats($userId),
        ];
    }
}
