<?php

namespace App\Services;

use App\Models\PointRule;
use App\Models\User;
use App\Models\UserDailyPoint;
use App\Models\UserMonthlyPoint;
use App\Models\UserPointHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class MonthlyPointService
{
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
                    'created_at' => now(),
                ]
            );

            if (! $history->wasRecentlyCreated) {
                return false;
            }

            $lockedUser->increment('total_points', $rule->points);

            $monthlyPoints = UserMonthlyPoint::query()
                ->where('user_id', $lockedUser->id)
                ->where('month', now()->month)
                ->where('year', now()->year)
                ->first();

            if ($monthlyPoints) {
                $monthlyPoints->increment('points', $rule->points);
            } else {
                UserMonthlyPoint::query()->create([
                    'user_id' => $lockedUser->id,
                    'month' => now()->month,
                    'year' => now()->year,
                    'points' => $rule->points,
                ]);
            }

            $dailyPoints = UserDailyPoint::query()
                ->where('user_id', $lockedUser->id)
                ->where('date', now()->toDateString())
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
                    'date' => now()->toDateString(),
                    'action' => $action,
                    'count' => 1,
                    'points' => $rule->points,
                ]);
            }

            $user->setAttribute('total_points', $lockedUser->total_points);

            return true;
        });

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
