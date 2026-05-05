<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Carts\CartRepositoryInterface;
use App\Repositories\Contracts\Purchases\PurchaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PurchaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PurchaseRepositoryInterface $purchaseRepository
    ) {
    }

     private function clearCartCache($userId)
    {
        Cache::tags(['cart', "user_{$userId}"])->flush();
        Cache::tags('user_profile')->flush();
    }
    public function purchase(Request $request)
    {
        $total = 0;
        $user = auth()->user();
        $cart = $this->cartRepository->findByUserId($user->id);
        $items = $this->cartRepository->getItemsByCartId($cart->id);
        $purchase = $this->purchaseRepository->create([
            "user_id" => $user,
        ]);
        foreach ($items as $item) {
            $total += $item->price;
            $this->purchaseRepository->createItem([
                "purchase_id" => $purchase->id,
                "image_id" => $item->image_id,
                "price" => $item->price,
            ]);
        }
        $this->cartRepository->deleteItemsByCartId($cart->id);
        $purchase->total = $total;
        $purchase->save();
        $this->clearCartCache($user->id);
        return $this->success(
            $purchase->load('items'),
            'purchased successfully'
        );
    }
}
