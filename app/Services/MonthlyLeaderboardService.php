<?php

namespace App\Services;

use App\Models\UserMonthlyPoint;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MonthlyLeaderboardService
{
    /** @return Collection<int, UserMonthlyPoint> */
    public function preview(int $limit = 10): Collection
    {
        $period = now();
        $limit = max(1, min($limit, 10));

        return $this->currentMonthQuery($period)
            ->select(['id', 'user_id', 'month', 'year', 'points'])
            ->with('user:id,name,image')
            ->whereHas('user')
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
        $period = now();

        $leaderboard = $this->currentMonthQuery($period)
            ->with('user')
            ->orderByDesc('points')
            ->orderBy('user_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $offset = ($leaderboard->currentPage() - 1) * $leaderboard->perPage();

        $leaderboard->getCollection()->each(function (UserMonthlyPoint $entry, int $index) use ($offset): void {
            $entry->setAttribute('rank', $offset + $index + 1);
        });

        return $leaderboard;
    }

    /** @return array{monthly_points: int, monthly_rank: ?int, month: string} */
    public function currentUserStats(int $userId): array
    {
        $period = now();
        $entry = $this->currentMonthQuery($period)
            ->where('user_id', $userId)
            ->first(['user_id', 'points']);

        if (! $entry) {
            return [
                'monthly_points' => 0,
                'monthly_rank' => null,
                'month' => $period->format('Y-m'),
            ];
        }

        $usersAhead = $this->currentMonthQuery($period)
            ->where(function (Builder $query) use ($entry): void {
                $query->where('points', '>', $entry->points)
                    ->orWhere(function (Builder $query) use ($entry): void {
                        $query->where('points', $entry->points)
                            ->where('user_id', '<', $entry->user_id);
                    });
            })
            ->count();

        return [
            'monthly_points' => (int) $entry->points,
            'monthly_rank' => $usersAhead + 1,
            'month' => $period->format('Y-m'),
        ];
    }

    private function currentMonthQuery(CarbonInterface $period): Builder
    {
        return UserMonthlyPoint::query()
            ->where('month', $period->month)
            ->where('year', $period->year);
    }
}
