<?php

namespace App\Repositories\Contracts\Purchases;

interface PurchaseRepositoryInterface
{
    public function findById(int $id);
    public function create(array $data);
    public function createItem(array $data);
    public function paginateAll(int $perPage = 10);
    public function paginateByType(string $type, int $perPage = 10);
    public function paginateByStatus(string $status, int $perPage = 10);
    public function count(): int;
    public function findWithUserAndItemsOrFail(int $id);
    public function findOrFail(int $id);
    public function findByUserId(int $userId);
    public function pluckIdsByUserId(int $userId);
    public function pluckImageIdsByPurchaseIds($purchaseIds);
    public function findByPaypalOrderId(string $paypalOrderId);
}
