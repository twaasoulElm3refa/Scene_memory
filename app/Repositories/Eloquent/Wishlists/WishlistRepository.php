<?php

namespace App\Repositories\Eloquent\Wishlists;

use App\Models\Wishlist;
use App\Repositories\Contracts\Wishlists\WishlistRepositoryInterface;
use App\Services\PointService;
use Illuminate\Support\Facades\DB;

class WishlistRepository implements WishlistRepositoryInterface
{
    public function __construct(private readonly PointService $pointService) {}

    public function eventIdsByUserId(int $userId)
    {
        return Wishlist::where('user_id', $userId)->pluck('event_id');
    }

    public function firstOrCreate(array $data)
    {
        return DB::transaction(function () use ($data) {
            $wishlist = Wishlist::query()->firstOrCreate($data);

            if ($wishlist->user && $wishlist->event) {
                $this->pointService->award(
                    $wishlist->user,
                    PointService::WISHLIST_CREATED,
                    $wishlist->event,
                    ['wishlist_id' => $wishlist->id]
                );
            }

            return $wishlist;
        });
    }

    public function findByEventAndUserOrFail(int $eventId, int $userId)
    {
        return Wishlist::where('event_id', $eventId)->where('user_id', $userId)->firstOrFail();
    }
}
