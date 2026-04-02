<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\cart;
use App\Models\cartItems;
use App\Models\purchases;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    use ApiResponse;

    public function purchase(Request $request)
    {
        $user=auth()->user();
        $cart=cart::where("user_id",$user->id)->first();
        $items=cartItems::where("cart_id",$cart->id)->get();
        $purchase= purchases::create([
            "user_id"=>$user->id,
        ]);

    }
}
