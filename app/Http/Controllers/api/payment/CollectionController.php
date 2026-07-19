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
use App\Models\Entitlement;
use App\Services\MinorMoney;

class CollectionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly MinorMoney $money,
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
            $event = Events::query()->whereKey($eventId)->where('is_active', true)->firstOrFail();

            // Get all images for this event
            $images = $event->images()->where('is_active', true)->orderBy('id')->get();

            if ($images->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event has no images'
                ], 422);
            }

            // Calculate total price before discount
            $owned = Entitlement::where('user_id', $user->id)->whereIn('media_id', $images->pluck('id'))->exists();
            if ($owned) {
                return response()->json(['success' => false, 'message' => 'One or more collection items are already owned'], 409);
            }

            $totalMinor = $images->sum(fn ($image) => $this->money->fromDecimal((string) $image->price));
            $finalMinor = intdiv($totalMinor * 90 + 50, 100);
            $discountMinor = $totalMinor - $finalMinor;

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
            $collectionItem = DB::transaction(function () use ($cart, $event, $images, $totalMinor, $discountMinor, $collectionImages) {
                CartItems::query()
                    ->where('cart_id', $cart->id)
                    ->where('type', 'single')
                    ->whereIn('image_id', $images->pluck('id'))
                    ->delete();

                return CartItems::updateOrCreate(
                    ['cart_id' => $cart->id, 'event_id' => $event->id, 'type' => 'collection'],
                    [
                        'image_id' => null,
                        'price' => $this->money->toDecimal((int) $totalMinor),
                        'discount' => $this->money->toDecimal((int) $discountMinor),
                        'collection_images' => $collectionImages,
                    ],
                );
            }, 5);

            // Clear cache
            $this->clearCartCache($user->id);

            return $this->success([
                'item' => $collectionItem,
                'event' => $event->only(['id', 'title', 'slug']),
                'total_images' => $images->count(),
                'total_before_discount' => $this->money->toDecimal((int) $totalMinor),
                'discount' => $this->money->toDecimal((int) $discountMinor),
                'total_after_discount' => $this->money->toDecimal((int) $finalMinor),
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
