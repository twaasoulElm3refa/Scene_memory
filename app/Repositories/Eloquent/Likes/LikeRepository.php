<?php

namespace App\Repositories\Eloquent\Likes;

use App\Models\Likes;
use App\Repositories\Contracts\Likes\LikeRepositoryInterface;

class LikeRepository implements LikeRepositoryInterface
{
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
        return Likes::create($data);
    }
}
