<?php

namespace App\Repositories\Contracts\Wishlists;

interface WishlistRepositoryInterface
{
    public function eventIdsByUserId(int $userId);
    public function firstOrCreate(array $data);
    public function findByEventAndUserOrFail(int $eventId, int $userId);
}
