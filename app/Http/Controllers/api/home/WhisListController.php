<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Wishlists\WishlistRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WhisListController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WishlistRepositoryInterface $wishlistRepository,
        private readonly EventRepositoryInterface $eventRepository
    ) {
    }

    public function me()
    {
        try {
            $userId = auth()->id();

            $events = Cache::tags(['wishlist', 'wishlist_user_' . $userId])
                ->remember("wishlist_user_{$userId}", 60 * 10, function () use ($userId) {
                    $whishlists = $this->wishlistRepository->eventIdsByUserId($userId);
                    return $this->eventRepository->wishlistEventsPaginated($whishlists, 5);
                });

            return $this->success($events, 'My wishLists');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function add()
    {
        try {
            $userId = auth()->id();

            $wishlist = DB::transaction(function () use ($userId) {
                return $this->wishlistRepository->firstOrCreate([
                    'user_id' => $userId,
                    'event_id' => request('id'),
                ]);
            });

            Cache::tags(['wishlist', 'wishlist_user_' . $userId])->flush();

            return $this->success($wishlist, 'Wishlist processed successfully');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $userId = auth()->id();
            $event = $this->eventRepository->findByIdOrFail((int) $id);
            $wishlist = $this->wishlistRepository->findByEventAndUserOrFail((int) $event->id, (int) $userId);
            if (auth()->id() !== $wishlist->user_id) {
                return $this->unauthorized('??? ????? ?? ???? ??? ???????');
            }
            $wishlist->delete();
            Cache::tags(['wishlist', 'wishlist_user_' . $userId])->flush();
            return $this->success([], '?? ??? ?????? ?? ??????? ?????');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('?????? ??? ?????', 404);
        } catch (\Throwable $th) {
            return $this->error('??? ??? ????? ?????: '.$th->getMessage(), 500);
        }
    }
}
