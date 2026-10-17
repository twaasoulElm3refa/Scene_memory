<?php

namespace App\Services;

use App\Models\UserWeeklyPoint;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WeeklyLeaderboardService
{
    public function __construct(private readonly WeeklyCompetitionPeriod $period) {}

    /** Live indexed reads deliberately avoid stale ranking/profile caches. */
    public function preview(int $limit = 10, ?CarbonInterface $at = null): Collection
    {
        return $this->query($at)->orderByDesc('points')->orderBy('user_id')
            ->limit(max(1, min($limit, 10)))->get()
            ->each(fn (UserWeeklyPoint $entry, int $index) => $entry->setAttribute('rank', $index + 1));
    }

    public function currentWeek(int $perPage = 15, ?int $page = null, ?CarbonInterface $at = null): LengthAwarePaginator
    {
        $leaders = $this->query($at)->orderByDesc('points')->orderBy('user_id')
            ->paginate(max(1, min($perPage, 100)), ['*'], 'page', $page);
        $offset = ($leaders->currentPage() - 1) * $leaders->perPage();
        $leaders->getCollection()->each(fn (UserWeeklyPoint $entry, int $index) => $entry->setAttribute('rank', $offset + $index + 1));

        return $leaders;
    }

    public function currentUserStats(int $userId): array
    {
        $start = $this->period->currentWeekStart();
        $entry = $this->query($start)->where('user_id', $userId)->first();
        $rank = null;

        if ($entry) {
            $rank = 1 + $this->query($start)->where(function (Builder $query) use ($entry): void {
                $query->where('points', '>', $entry->points)
                    ->orWhere(fn (Builder $query) => $query->where('points', $entry->points)->where('user_id', '<', $entry->user_id));
            })->count();
        }

        return [
            'weekly_points' => (int) ($entry?->points ?? 0),
            'weekly_rank' => $rank,
            ...$this->period->metadata($start),
        ];
    }

    private function query(?CarbonInterface $at): Builder
    {
        return UserWeeklyPoint::query()->where('week_start', $this->period->weekIdentifier($at))
            ->where('points', '>', 0)->whereHas('user')->with('user:id,name,image');
    }
}
