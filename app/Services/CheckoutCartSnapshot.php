<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItems;
use App\Models\EventsImges;
use App\Models\PurchaseItems;
use App\Models\Purchases;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutCartSnapshot
{
    private const COLLECTION_DISCOUNT_RATE = 0.10;

    public function create(array $data): Purchases
    {
        return DB::transaction(function () use ($data) {
            $userId = (int) ($data['user_id'] ?? 0);

            if ($userId < 1) {
                throw new RuntimeException('An authenticated user is required.');
            }

            $providedKey = $data['idempotency_key'] ?? null;
            $idempotencyKey = $providedKey
                ? $this->idempotencyKey($userId, (string) $providedKey)
                : null;

            if ($idempotencyKey) {
                $existing = Purchases::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('type', 'checkout')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $cart = Cart::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                throw new RuntimeException('Cart not found.');
            }

            $cartItems = CartItems::query()
                ->where('cart_id', $cart->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw new RuntimeException('Cart is empty.');
            }

            $idempotencyKey ??= $this->idempotencyKey(
                $userId,
                $cartItems->map(fn (CartItems $item) => $item->id.':'.$item->updated_at?->getTimestamp())->implode('|'),
            );

            $existing = Purchases::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('type', 'checkout')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $snapshotItems = $cartItems->flatMap(
                fn (CartItems $item) => $this->snapshotItem($item),
            );
            $totalCents = $snapshotItems->sum('price_cents');

            if ($totalCents < 1) {
                throw new RuntimeException('Cart total must be greater than zero.');
            }

            $purchase = Purchases::create([
                'idempotency_key' => $idempotencyKey,
                'user_id' => $userId,
                'payment_method' => 'paypal',
                'amount' => $this->fromCents($totalCents),
                'currency' => config('paypal.currency', 'USD'),
                'description' => $data['description'] ?? 'Order Payment',
                'type' => 'checkout',
                'status' => 'pending',
            ]);

            $snapshotItems->each(function (array $item) use ($purchase) {
                PurchaseItems::create([
                    'purchase_id' => $purchase->id,
                    'image_id' => $item['image_id'],
                    'source_cart_item_id' => $item['source_cart_item_id'],
                    'price' => $this->fromCents($item['price_cents']),
                    'purchased_type' => $item['purchased_type'],
                ]);
            });

            return $purchase->fresh('items');
        });
    }

    private function snapshotItem(CartItems $cartItem): Collection
    {
        if ($cartItem->type !== 'collection') {
            $image = EventsImges::query()->lockForUpdate()->find($cartItem->image_id);

            if (! $image) {
                throw new RuntimeException("Cart image {$cartItem->image_id} no longer exists.");
            }

            return collect([[
                'image_id' => $image->id,
                'source_cart_item_id' => $cartItem->id,
                'price_cents' => $this->toCents($image->price),
                'purchased_type' => 'single',
            ]]);
        }

        $imageIds = collect($cartItem->collection_images_array)
            ->pluck('id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($imageIds->isEmpty()) {
            throw new RuntimeException('Collection has no purchasable images.');
        }

        $images = EventsImges::query()
            ->whereIn('id', $imageIds)
            ->when($cartItem->event_id, fn ($query) => $query->where('event_id', $cartItem->event_id))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($images->count() !== $imageIds->count()) {
            throw new RuntimeException('One or more collection images are no longer available.');
        }

        $rawPrices = $imageIds->map(fn (int $id) => $this->toCents($images[$id]->price));
        $rawTotal = $rawPrices->sum();
        $discountedTotal = (int) round($rawTotal * (1 - self::COLLECTION_DISCOUNT_RATE));
        $allocated = 0;

        return $imageIds->map(function (int $id, int $index) use (
            $cartItem,
            $rawPrices,
            $rawTotal,
            $discountedTotal,
            &$allocated,
        ) {
            $isLast = $index === $rawPrices->count() - 1;
            $price = $isLast
                ? $discountedTotal - $allocated
                : (int) round($rawPrices[$index] * $discountedTotal / max($rawTotal, 1));
            $allocated += $price;

            return [
                'image_id' => $id,
                'source_cart_item_id' => $cartItem->id,
                'price_cents' => $price,
                'purchased_type' => 'collection',
            ];
        });
    }

    private function idempotencyKey(int $userId, string $value): string
    {
        return hash('sha256', "checkout|{$userId}|{$value}");
    }

    private function toCents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function fromCents(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }
}
