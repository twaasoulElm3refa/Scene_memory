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

                    return $cart->cartItems->map(fn ($item) => $this->formatCartItem($item))->values();
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
            $cart = $this->cartRepository->findByUserId($user->id);
            if (!$cart) {
                return $this->error('Cart not Found', 404);
            }

            $item = $this->cartRepository->findItemByCartIdAndItemId($cart->id, (int) $id)
                ?? $this->cartRepository->findItemByCartIdAndImageId($cart->id, (int) $id);

            if (!$item) {
                return $this->error('Item not found in cart', 404);
            }
            $item->delete();
            $this->clearCartCache($user->id);
            return $this->success(null, 'Item deleted from cart successfully');
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

    private function formatCartItem($item): array
    {
        if ($item->type === 'collection') {
            $collectionImages = collect($item->collection_images_array)->map(function ($image) {
                $path = $image['image_url']
                    ?? $image['preview_url']
                    ?? $image['url']
                    ?? $image['full_url']
                    ?? $image['path']
                    ?? '';

                return [
                    'id' => $image['id'] ?? null,
                    'url' => $path,
                    'preview_url' => $image['preview_url'] ?? $path,
                    'image_url' => $image['image_url'] ?? $this->storageUrl($path),
                    'price' => $image['price'] ?? 0,
                    'title' => $image['title'] ?? null,
                    'name' => $image['name'] ?? null,
                ];
            })->values()->all();

            return [
                'id' => $item->id,
                'cart_item_id' => $item->id,
                'type' => 'collection',
                'event_id' => $item->event_id,
                'image_id' => null,
                'name' => $item->event?->translation?->title ?? $item->event?->title ?? 'Full Collection',
                'price' => $item->price,
                'discount' => $item->discount,
                'final_price' => $item->final_price,
                'collection_images' => $collectionImages,
                'collection_images_count' => count($collectionImages),
            ];
        }

        $image = $item->items;

        return [
            'id' => $item->id,
            'cart_item_id' => $item->id,
            'type' => $item->type ?: 'single',
            'event_id' => $item->event_id,
            'image_id' => $item->image_id,
            'media_id' => $image?->id,
            'full_url' => $image?->full_url,
            'image_url' => $this->storageUrl($image?->full_url),
            'price' => $item->price ?? $image?->price ?? 0,
            'discount' => $item->discount ?? 0,
            'final_price' => $item->final_price,
            'name' => 'Product',
        ];
    }

    private function storageUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cleanPath = str_replace('\\', '/', trim($path));

        if (str_starts_with($cleanPath, 'storage/')) {
            return asset($cleanPath);
        }

        if (str_starts_with($cleanPath, '/storage/')) {
            return asset(ltrim($cleanPath, '/'));
        }

        if (str_starts_with($cleanPath, 'public/')) {
            $cleanPath = preg_replace('/^public\//', '', $cleanPath);
        }

        return asset('storage/' . ltrim($cleanPath, '/'));
    }

}
