<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\cart;
use App\Models\cartItems;
use App\Models\purchases;
use App\Models\purchase_items;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PurchaseController extends Controller
{
    use ApiResponse;

     private function clearCartCache($userId)
    {
        Cache::tags(['cart', "user_{$userId}"])->flush();
        Cache::tags('user_profile')->flush();
    }
    public function purchase(Request $request)
    {
        $total = 0;
        $user = auth()->user();
        $cart = cart::where("user_id", $user->id)->first();
        $items = cartItems::where("cart_id", $cart->id)->get();
        $purchase = purchases::create([
            "user_id" => $user,
        ]);
        foreach ($items as $item) {
            $total += $item->price;
            purchase_items::create([
                "purchase_id" => $purchase->id,
                "image_id" => $item->image_id,
                "price" => $item->price,
            ]);
        }
        cartItems::where('cart_id', $cart->id)->delete();
        $purchase->total = $total;
        $purchase->save();
        $this->clearCartCache($user->id);
        return $this->success(
            $purchase->load('items'),
            'purchased successfully'
        );
    }
}
