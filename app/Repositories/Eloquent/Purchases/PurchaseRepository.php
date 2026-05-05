<?php

namespace App\Repositories\Eloquent\Purchases;

use App\Models\purchase_items;
use App\Models\purchases;
use App\Repositories\Contracts\Purchases\PurchaseRepositoryInterface;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    public function findById(int $id)
    {
        return purchases::find($id);
    }

    public function create(array $data)
    {
        return purchases::create($data);
    }

    public function createItem(array $data)
    {
        return purchase_items::create($data);
    }

    public function paginateAll(int $perPage = 10)
    {
        return purchases::query()->select(['id', 'user_id', 'amount', 'status', 'type', 'mail_sent', 'paid_at', 'created_at'])->with(['user:id,name,email'])->latest('id')->paginate($perPage);
    }

    public function paginateByType(string $type, int $perPage = 10)
    {
        return purchases::query()->select(['id', 'user_id', 'amount', 'status', 'type', 'mail_sent', 'paid_at', 'created_at'])->with(['user:id,name,email'])->where('type', $type)->latest('id')->paginate($perPage);
    }

    public function paginateByStatus(string $status, int $perPage = 10)
    {
        return purchases::query()->select(['id', 'user_id', 'amount', 'status', 'type', 'mail_sent', 'paid_at', 'created_at'])->with(['user:id,name,email'])->where('status', $status)->latest('id')->paginate($perPage);
    }

    public function count(): int
    {
        return purchases::count();
    }

    public function findWithUserAndItemsOrFail(int $id)
    {
        return purchases::with('user', 'items')->findOrFail($id);
    }

    public function findOrFail(int $id)
    {
        return purchases::query()->findOrFail($id);
    }

    public function findByUserId(int $userId)
    {
        return purchases::where('user_id', $userId)->get();
    }

    public function pluckIdsByUserId(int $userId)
    {
        return purchases::where('user_id', $userId)->pluck('id');
    }

    public function pluckImageIdsByPurchaseIds($purchaseIds)
    {
        return purchase_items::whereIn('purchase_id', $purchaseIds)->pluck('image_id');
    }

    public function findByPaypalOrderId(string $paypalOrderId)
    {
        return purchases::where('paypal_order_id', $paypalOrderId)->first();
    }
}
