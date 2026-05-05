<?php

namespace App\Repositories\Eloquent\Wishlists;

use App\Models\Wishlist;
use App\Repositories\Contracts\Wishlists\WishlistRepositoryInterface;

class WishlistRepository implements WishlistRepositoryInterface
{
    public function eventIdsByUserId(int $userId)
    {
        return Wishlist::where('user_id', $userId)->pluck('event_id');
    }

    public function firstOrCreate(array $data)
    {
        return Wishlist::firstOrCreate($data);
    }

    public function findByEventAndUserOrFail(int $eventId, int $userId)
    {
        return Wishlist::where('event_id', $eventId)->where('user_id', $userId)->firstOrFail();
    }
}
