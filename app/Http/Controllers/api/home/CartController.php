<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Carts\CartRepositoryInterface;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {
    }

    private function clearCartCache($userId)
    {
        Cache::tags(['cart', "user_{$userId}"])->flush();
        Cache::tags('user_profile')->flush();
    }
    public function addToCart()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            $id = request('id');
            if (!$id) {
                return $this->error('Image ID is required', 400);
            }
            $cart = $this->cartRepository->firstOrCreateByUserId($user->id);
            $image = $this->eventImageRepository->findById((int) $id);
            if (!$image) {
                return $this->error('Image not found', 404);
            }
            $item = $this->cartRepository->firstOrCreateItem([
                'cart_id'  => $cart->id,
                'image_id' => $image->id,
                'price'    => $image->price,
            ]);
            $this->clearCartCache($user->id);
            return $this->success($item, 'Image added to cart successfully');

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function cart()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $cacheKey = "cart_user_{$user->id}";

            $images = Cache::tags(['cart', "user_{$user->id}"])
                ->remember($cacheKey, now()->addMinutes(10), function () use ($user) {
                    $cart = $this->cartRepository->findWithItemsByUserId($user->id);
                        if (!$cart) {
                            $cart = $this->cartRepository->firstOrCreateByUserId($user->id);
                        }
                    $cartitems = $this->cartRepository->pluckImageIdsByCartId($cart->id);
                    $images = $this->eventImageRepository->whereInIds($cartitems)->select(['id', 'full_url', 'price'])->get();
                    return $images;
                });

            return $this->success($images, "Cart Fetched Successfully");

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function deleteFromCart($id)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            $item = $this->cartRepository->findItemByImageId((int) $id);
            if (!$item) {
                return $this->error('Image not found in cart', 404);
            }
            $item->delete();
            $this->clearCartCache($user->id);
            return $this->success(null, 'Image deleted from cart successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function clearCart()
    {
        $user = auth()->user();
        $cart = $this->cartRepository->findByUserId($user->id);
        if (!$cart) {
            return $this->error('Cart not Found',404);
        }
        $this->cartRepository->deleteItemsByCartId($cart->id);
        $this->clearCartCache($user->id);
        return $this->success(null, 'Cart cleared successfully');
    }

}
