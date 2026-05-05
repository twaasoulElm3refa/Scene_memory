<?php

namespace App\Repositories\Eloquent\Carts;

use App\Models\cart;
use App\Models\cartItems;
use App\Repositories\Contracts\Carts\CartRepositoryInterface;

class CartRepository implements CartRepositoryInterface
{
    public function firstOrCreateByUserId(int $userId): cart
    {
        return cart::firstOrCreate(['user_id' => $userId]);
    }

    public function findByUserId(int $userId): ?cart
    {
        return cart::where('user_id', $userId)->first();
    }

    public function findWithItemsByUserId(int $userId): ?cart
    {
        return cart::with('cartItems.items')->where('user_id', $userId)->first();
    }

    public function getItemsByCartId(int $cartId)
    {
        return cartItems::where('cart_id', $cartId)->get();
    }

    public function createItem(array $data)
    {
        return cartItems::create($data);
    }

    public function firstOrCreateItem(array $attributes)
    {
        return cartItems::firstOrCreate($attributes);
    }

    public function deleteItemsByCartId(int $cartId): void
    {
        cartItems::where('cart_id', $cartId)->delete();
    }

    public function findItemByImageId(int $imageId)
    {
        return cartItems::where('image_id', $imageId)->first();
    }

    public function pluckImageIdsByCartId(int $cartId)
    {
        return cartItems::where('cart_id', $cartId)->pluck('image_id');
    }
}
