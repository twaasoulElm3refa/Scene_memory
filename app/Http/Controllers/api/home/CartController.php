<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\cart;
use App\Models\cartItems;
use App\Models\eventsImges;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{
    use ApiResponse;

    private function clearCartCache($userId)
    {
        Cache::tags(['cart', "user_{$userId}"])->flush();
    }
    public function addToCart()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            $id = request('id');
            if (!$id) {
                return $this->error('Image ID is required', 400);
            }
            $cart = cart::firstOrCreate([
                'user_id' => $user->id
            ]);
            $image = eventsImges::find($id);
            if (!$image) {
                return $this->error('Image not found', 404);
            }
            $item = cartItems::firstOrCreate([
                'cart_id'  => $cart->id,
                'image_id' => $image->id,
                'price'    => $image->price,
            ]);
            $this->clearCartCache($user->id);
            return $this->success($item, 'Image added to cart successfully');

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }



    public function cart()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $cacheKey = "cart_user_{$user->id}";

            $cart = Cache::tags(['cart', "user_{$user->id}"])
                ->remember($cacheKey, now()->addMinutes(10), function () use ($user) {

                    $cart = cart::with('cartItems')
                        ->where("user_id", $user->id)
                        ->first();

                    if (!$cart) {
                        $cart = cart::create([
                            "user_id" => $user->id
                        ]);

                        $cart->load('cartItems');
                    }

                    return $cart;
                });

            return $this->success($cart, "Cart Fetched Successfully");

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

}
