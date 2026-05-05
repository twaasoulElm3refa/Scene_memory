<?php

namespace App\Repositories\Contracts\Carts;

use App\Models\cart;

interface CartRepositoryInterface
{
    public function firstOrCreateByUserId(int $userId): cart;
    public function findByUserId(int $userId): ?cart;
    public function findWithItemsByUserId(int $userId): ?cart;
    public function getItemsByCartId(int $cartId);
    public function createItem(array $data);
    public function firstOrCreateItem(array $attributes);
    public function deleteItemsByCartId(int $cartId): void;
    public function findItemByImageId(int $imageId);
    public function pluckImageIdsByCartId(int $cartId);
}
