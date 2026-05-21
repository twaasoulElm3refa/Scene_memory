<?php

namespace App\Repositories\Eloquent\Purchases;

use App\Models\PurchaseItems;
use App\Models\Purchases;
use App\Repositories\Contracts\Purchases\PurchaseRepositoryInterface;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    public function findById(int $id)
    {
        return Purchases::find($id);
    }

    public function create(array $data)
    {
        return Purchases::create($data);
    }

    public function createItem(array $data)
    {
        return PurchaseItems::create($data);
    }

    public function paginateAll(int $perPage = 10)
    {
        return Purchases::query()->select(['id', 'user_id', 'amount', 'status', 'type', 'mail_sent', 'paid_at', 'created_at'])->with(['user:id,name,email'])->latest('id')->paginate($perPage);
    }

    public function paginateByType(string $type, int $perPage = 10)
    {
        return Purchases::query()->select(['id', 'user_id', 'amount', 'status', 'type', 'mail_sent', 'paid_at', 'created_at'])->with(['user:id,name,email'])->where('type', $type)->latest('id')->paginate($perPage);
    }

    public function paginateByStatus(string $status, int $perPage = 10)
    {
        return Purchases::query()->select(['id', 'user_id', 'amount', 'status', 'type', 'mail_sent', 'paid_at', 'created_at'])->with(['user:id,name,email'])->where('status', $status)->latest('id')->paginate($perPage);
    }

    public function count(): int
    {
        return Purchases::count();
    }

    public function findWithUserAndItemsOrFail(int $id)
    {
        return Purchases::with('user', 'items')->findOrFail($id);
    }

    public function findOrFail(int $id)
    {
        return Purchases::query()->findOrFail($id);
    }

    public function findByUserId(int $userId)
    {
        return Purchases::where('user_id', $userId)->get();
    }

    public function pluckIdsByUserId(int $userId)
    {
        return Purchases::where('user_id', $userId)->pluck('id');
    }

    public function pluckImageIdsByPurchaseIds($purchaseIds)
    {
        return PurchaseItems::whereIn('purchase_id', $purchaseIds)->pluck('image_id');
    }

    public function findByPaypalOrderId(string $paypalOrderId)
    {
        return Purchases::where('paypal_order_id', $paypalOrderId)->first();
    }
}
