<?php

namespace App\Repositories\Eloquent\Carts;

use App\Models\Cart;
use App\Models\cartItems;
use App\Repositories\Contracts\Carts\CartRepositoryInterface;

class CartRepository implements CartRepositoryInterface
{
    public function firstOrCreateByUserId(int $userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId]);
    }

    public function findByUserId(int $userId): ?Cart
    {
        return Cart::where('user_id', $userId)->first();
    }

    public function findWithItemsByUserId(int $userId): ?Cart
    {
        return Cart::with('cartItems.items')->where('user_id', $userId)->first();
    }

    public function getItemsByCartId(int $cartId)
    {
        return CartItems::where('cart_id', $cartId)->get();
    }

    public function createItem(array $data)
    {
        return CartItems::create($data);
    }

    public function firstOrCreateItem(array $attributes)
    {
        return CartItems::firstOrCreate($attributes);
    }

    public function deleteItemsByCartId(int $cartId): void
    {
        CartItems::where('cart_id', $cartId)->delete();
    }

    public function findItemByImageId(int $imageId)
    {
        return CartItems::where('image_id', $imageId)->first();
    }

    public function pluckImageIdsByCartId(int $cartId)
    {
        return CartItems::where('cart_id', $cartId)->pluck('image_id');
    }
}
