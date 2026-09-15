<?php

namespace App\Services;

use App\Models\PointRule;
use App\Models\User;
use App\Models\UserDailyPoint;
use App\Models\UserPointHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class PointService
{
    public const EVENT_CREATED = 'event.created';

    public const LIKE_CREATED = 'like.created';

    public const COMMENT_CREATED = 'comment.created';

    public const COMMENT_REPLY = 'comment.reply';

    public const COMMENT_IMAGE = 'comment.image';

    public const COMMENT_INTERACTION = 'comment.interaction';

    public const WISHLIST_CREATED = 'wishlist.created';

    /**
     * Award an active point rule once for the same user, action, and reference.
     */
    public function award(User $user, string $action, Model $reference, array $metadata = []): bool
    {
        if (! $reference->exists) {
            return false;
        }

        $awarded = DB::transaction(function () use ($user, $action, $reference, $metadata): bool {
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
                    'reference_type' => $reference->getMorphClass(),
                    'reference_id' => $reference->getKey(),
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
