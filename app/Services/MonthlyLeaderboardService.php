<?php

namespace App\Services;

use App\Models\UserMonthlyPoint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MonthlyLeaderboardService
{
    /** @return Collection<int, UserMonthlyPoint> */
    public function preview(int $limit = 10): Collection
    {
        $period = now();
        $limit = max(1, min($limit, 10));

        return UserMonthlyPoint::query()
            ->select(['id', 'user_id', 'month', 'year', 'points'])
            ->with('user:id,name,image')
            ->whereHas('user')
            ->where('month', $period->month)
            ->where('year', $period->year)
            ->orderByDesc('points')
            ->orderBy('user_id')
            ->limit($limit)
            ->get()
            ->each(function (UserMonthlyPoint $entry, int $index): void {
                $entry->setAttribute('rank', $index + 1);
            });
    }

    public function currentMonth(int $perPage = 15, ?int $page = null): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        $leaderboard = UserMonthlyPoint::query()
            ->with('user')
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->orderByDesc('points')
            ->orderBy('user_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $offset = ($leaderboard->currentPage() - 1) * $leaderboard->perPage();

        $leaderboard->getCollection()->each(function (UserMonthlyPoint $entry, int $index) use ($offset): void {
            $entry->setAttribute('rank', $offset + $index + 1);
        });

        return $leaderboard;
    }
}
