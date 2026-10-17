<?php

namespace App\Services;

use App\Models\PointRule;
use App\Models\User;
use App\Models\UserDailyPoint;
use App\Models\UserPointHistory;
use App\Models\UserWeeklyPoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class WeeklyPointService
{
    public function __construct(private readonly WeeklyCompetitionPeriod $period) {}

    /**
     * Award an active point rule once for the same user, action, and reference.
     */
    public function addPoints(
        User $user,
        string $action,
        string $referenceType,
        int $referenceId,
        array $metadata = []
    ): bool {
        $awarded = DB::transaction(function () use (
            $user,
            $action,
            $referenceType,
            $referenceId,
            $metadata
        ): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $rule = PointRule::query()
                ->where('action', $action)
                ->where('status', true)
                ->first();

            if (! $rule) {
                return false;
            }

            // Select one instant after acquiring the period lock. A lock wait may
            // span Saturday midnight, so re-evaluate before recording the award.
            do {
                $period = $this->period->metadata();
                DB::table('competition_weeks')->insertOrIgnore([
                    'week_start' => $period['week_start'],
                    'week_end' => $period['week_end'],
                    'timezone' => $period['timezone'],
                ]);
                $week = DB::table('competition_weeks')->where('week_start', $period['week_start'])
                    ->sharedLock()->first();
                $awardedAt = CarbonImmutable::now('UTC');
            } while ($this->period->weekIdentifier($awardedAt) !== $period['week_start']);

            if ($week->finalized_at !== null) {
                throw new \LogicException('Cannot award points to a finalized competition.');
            }

            $history = UserPointHistory::query()->firstOrCreate(
                [
                    'user_id' => $lockedUser->id,
                    'action' => $action,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ],
                [
                    'points' => $rule->points,
                    'metadata' => $metadata ?: null,
                    'created_at' => $awardedAt,
                ]
            );

            if (! $history->wasRecentlyCreated) {
                return false;
            }

            $lockedUser->increment('total_points', $rule->points);

            $weeklyPoints = UserWeeklyPoint::query()->firstOrCreate(
                ['user_id' => $lockedUser->id, 'week_start' => $period['week_start']],
                ['week_end' => $period['week_end'], 'points' => 0]
            );
            $weeklyPoints->increment('points', $rule->points);

            $dailyPoints = UserDailyPoint::query()
                ->where('user_id', $lockedUser->id)
                ->where('date', $awardedAt->setTimezone(config('app.timezone'))->toDateString())
                ->where('action', $action)
                ->first();

            if ($dailyPoints) {
                $dailyPoints->forceFill([
                    'count' => $dailyPoints->count + 1,
                    'points' => $dailyPoints->points + $rule->points,
                ])->save();
            } else {
                UserDailyPoint::query()->create([
                    'user_id' => $lockedUser->id,
                    'date' => $awardedAt->setTimezone(config('app.timezone'))->toDateString(),
                    'action' => $action,
                    'count' => 1,
                    'points' => $rule->points,
                ]);
            }

            $user->setAttribute('total_points', $lockedUser->total_points);

            return true;
        }, 5);

        if ($awarded) {
            DB::afterCommit(function () use ($user): void {
                $cacheKey = 'user_profile_'.$user->getKey();

                try {
                    Cache::tags(['user_profile', 'user_'.$user->getKey()])->forget($cacheKey);
                } catch (Throwable) {
                    Cache::forget($cacheKey);
                }
            });
        }

        return $awarded;
    }
}
