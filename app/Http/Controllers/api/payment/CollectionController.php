<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\concerns\ApiResponse;
use App\Models\Events;
use App\Models\CartItems;
use App\Repositories\Contracts\Carts\CartRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {}

    /**
     * Add full event collection to cart with 10% discount
     * POST /api/collections/{event_id}/add-to-cart
     */
    public function addCollectionToCart(Request $request, $eventId): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        try {
            $event = Events::findOrFail($eventId);

            // Get all images for this event
            $images = $event->images()->get();

            if ($images->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event has no images'
                ], 422);
            }

            // Calculate total price before discount
            $totalPrice = $images->sum('price');

            // Apply 10% discount
            $discountAmount = $totalPrice * 0.10;
            $finalPrice = $totalPrice - $discountAmount;

            // Get or create cart
            $cart = $this->cartRepository->findByUserId($user->id)
                ?? $this->cartRepository->create(['user_id' => $user->id]);

            $collectionImages = $images->map(function($img) {
                return [
                    'id' => $img->id,
                    'url' => $img->full_url,
                    'preview_url' => $img->full_url,
                    'price' => $img->price,
                ];
            })->values()->all();

            // Create collection cart item
            $collectionItem = CartItems::create([
                'cart_id' => $cart->id,
                'event_id' => $event->id,
                'image_id' => null, // null for collections
                'type' => 'collection',
                'price' => $totalPrice,
                'discount' => $discountAmount,
                'collection_images' => $collectionImages,
            ]);

            // Clear cache
            $this->clearCartCache($user->id);

            return $this->success([
                'item' => $collectionItem,
                'event' => $event->only(['id', 'title', 'slug']),
                'total_images' => $images->count(),
                'total_before_discount' => $totalPrice,
                'discount' => $discountAmount,
                'total_after_discount' => $finalPrice,
                'collection_images' => $collectionImages,
            ], 'Collection added to cart successfully');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get collection details with pricing
     * GET /api/collections/{event_id}
     */
    public function getCollectionDetails($eventId): JsonResponse
    {
        try {
            $event = Events::with('images')->findOrFail($eventId);

            $images = $event->images()->get();
            $totalPrice = $images->sum('price');
            $discountAmount = $totalPrice * 0.10;
            $finalPrice = $totalPrice - $discountAmount;

            return $this->success([
                'event_id' => $event->id,
                'event_title' => $event->title,
                'total_images' => $images->count(),
                'total_before_discount' => $totalPrice,
                'discount_percentage' => 10,
                'discount_amount' => $discountAmount,
                'total_after_discount' => $finalPrice,
                'images_preview' => $images->map(function($img) {
                    return [
                        'id' => $img->id,
                        'url' => $img->full_url,
                        'price' => $img->price,
                    ];
                })->take(5),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    private function clearCartCache($userId)
    {
        \Illuminate\Support\Facades\Cache::tags(['cart', "user_{$userId}"])->flush();
        \Illuminate\Support\Facades\Cache::tags('user_profile')->flush();
    }
}
