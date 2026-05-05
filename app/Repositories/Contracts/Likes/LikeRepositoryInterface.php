<?php

namespace App\Repositories\Contracts\Likes;

interface LikeRepositoryInterface
{
    public function countByEventId(int $eventId): int;
    public function countByUserAndEvent(int $userId, int $eventId): int;
    public function create(array $data);
}
