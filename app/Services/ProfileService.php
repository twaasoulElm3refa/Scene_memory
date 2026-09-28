<?php

namespace App\Services;

use App\Repositories\Contracts\Auth\ProfileRepositoryInterface;

class ProfileService
{
    public function __construct(
        protected ProfileRepositoryInterface $repository,
        protected MonthlyLeaderboardService $monthlyLeaderboardService
    ) {}

    public function activity(
        int $userId,
        array $filters = []
    ): array {
        return [
            ...$this->repository->getProfileActivity($userId, $filters),
            'monthly_statistics' => $this->monthlyLeaderboardService->currentUserStats($userId),
        ];
    }
}
