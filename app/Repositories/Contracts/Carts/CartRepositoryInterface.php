<?php

namespace App\Repositories\Contracts\Carts;

use App\Models\Cart;

interface CartRepositoryInterface
{
    public function firstOrCreateByUserId(int $userId): Cart;
    public function findByUserId(int $userId): ?Cart;
    public function findWithItemsByUserId(int $userId): ?Cart;
    public function create(array $data): Cart;
    public function getItemsByCartId(int $cartId);
    public function createItem(array $data);
    public function firstOrCreateItem(array $attributes);
    public function deleteItemsByCartId(int $cartId): void;
    public function findItemByImageId(int $imageId);
    public function findItemByCartIdAndImageId(int $cartId, int $imageId);
    public function findItemByCartIdAndItemId(int $cartId, int $itemId);
    public function pluckImageIdsByCartId(int $cartId);
}
