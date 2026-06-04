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
            $total += $this->cartItemTotal($item);

            // Handle collection items
            if ($item->type === 'collection' && $item->collection_images) {
                $collectionImages = $item->collection_images_array;
                foreach ($collectionImages as $img) {
                    $this->purchaseRepository->createItem([
                        "purchase_id" => $purchase->id,
                        "image_id" => $img['id'],
                        "price" => $img['price'],
                        "purchased_type" => "collection", // Track that it was from a collection
                    ]);
                }
            } else {
                // Handle single image items
                $this->purchaseRepository->createItem([
                    "purchase_id" => $purchase->id,
                    "image_id" => $item->image_id,
                    "price" => $item->price,
                ]);
            }
        }

        $this->cartRepository->deleteItemsByCartId($cart->id);
        $purchase->total = $total;
        $purchase->type = $this->determinePurchaseType($items);
        $purchase->save();
        $this->clearCartCache($user->id);

        return $this->success(
            $purchase->load('items'),
            'purchased successfully'
        );
    }

    /**
     * Determine purchase type based on cart items
     */
    private function determinePurchaseType($items)
    {
        $hasCollection = $items->whereNotNull('event_id')->where('type', 'collection')->count() > 0;
        $hasSingleItems = $items->whereNull('event_id')->where('type', 'single')->count() > 0;

        if ($hasCollection && $hasSingleItems) {
            return 'mixed'; // Both collections and single items
        } elseif ($hasCollection) {
            return 'collection';
        } else {
            return 'cart_purchase';
        }
    }

    private function cartItemTotal($item): float
    {
        if ($item->type === 'collection') {
            return max((float) $item->price - (float) $item->discount, 0);
        }

        return (float) $item->price;
    }
}
