<?php

namespace App\Repositories\Eloquent\Likes;

use App\Models\Likes;
use App\Repositories\Contracts\Likes\LikeRepositoryInterface;
use App\Services\PointService;
use Illuminate\Support\Facades\DB;

class LikeRepository implements LikeRepositoryInterface
{
    public function __construct(private readonly PointService $pointService) {}

    public function countByEventId(int $eventId): int
    {
        return Likes::where('event_id', $eventId)->count();
    }

    public function countByUserAndEvent(int $userId, int $eventId): int
    {
        return Likes::where('user_id', $userId)->where('event_id', $eventId)->count();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $like = Likes::query()->firstOrCreate([
                'user_id' => $data['user_id'],
                'event_id' => $data['event_id'],
            ]);

            if ($like->user && $like->event) {
                $this->pointService->award(
                    $like->user,
                    PointService::LIKE_CREATED,
                    $like->event,
                    ['like_id' => $like->id]
                );
            }

            return $like;
        });
    }
}
