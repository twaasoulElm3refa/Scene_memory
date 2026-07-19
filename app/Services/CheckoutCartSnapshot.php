<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Models\Cart;
use App\Models\CartItems;
use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Payment;
use App\Models\PurchaseItems;
use App\Models\Purchases;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckoutCartSnapshot
{
    private const COLLECTION_DISCOUNT_PERCENT = 10;

    public function __construct(private readonly MinorMoney $money) {}

    /**
     * @return array{order: Purchases, payment: Payment}
     */
    public function create(array $data, string $method): array
    {
        return DB::transaction(function () use ($data, $method) {
            $userId = (int) ($data['user_id'] ?? 0);
            $orderType = (string) ($data['type'] ?? 'cart');
            if ($userId < 1) {
                throw new CommerceException('An authenticated user is required.', 401);
            }

            $idempotencyKey = hash('sha256', implode('|', [
                $method,
                $userId,
                $orderType,
                (string) ($data['idempotency_key'] ?? ''),
            ]));

            $existingPayment = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                if ($existingPayment->operation !== 'purchase' || $existingPayment->method !== $method) {
                    throw new CommerceException('Idempotency key belongs to another operation.', 409);
                }
                if (in_array($existingPayment->status, ['failed', 'cancelled'], true)) {
                    throw new CommerceException('Use a new idempotency key for a failed payment.', 409);
                }

                return [
                    'order' => $existingPayment->order()->with('items')->firstOrFail(),
                    'payment' => $existingPayment,
                ];
            }

            $snapshot = $this->buildSnapshot($userId, $orderType, $data);
            if ($snapshot->isEmpty()) {
                throw new CommerceException('No purchasable media remain in this order.', 422);
            }

            $mediaIds = $snapshot->pluck('image_id')->unique()->values();
            $owned = Entitlement::query()
                ->where('user_id', $userId)
                ->whereIn('media_id', $mediaIds)
                ->pluck('media_id');
            if ($owned->isNotEmpty()) {
                throw new CommerceException('One or more media items are already owned.', 409);
            }

            $totalMinor = (int) $snapshot->sum('price_minor');
            if ($totalMinor < 1) {
                throw new CommerceException('Order total must be greater than zero.', 422);
            }

            $snapshotHash = hash('sha256', json_encode($snapshot->map(fn ($item) => [
                $item['image_id'],
                $item['price_minor'],
                $item['purchased_type'],
            ])->values()->all(), JSON_THROW_ON_ERROR));

            $order = Purchases::create([
                'idempotency_key' => $idempotencyKey,
                'snapshot_hash' => $snapshotHash,
                'user_id' => $userId,
                'payment_method' => $method,
                'amount' => $this->money->toDecimal($totalMinor),
                'amount_minor' => $totalMinor,
                'currency' => strtoupper((string) config('paypal.currency', 'USD')),
                'description' => $data['description'] ?? $this->description($orderType),
                'type' => 'checkout',
                'order_type' => $orderType,
                'status' => 'pending',
            ]);

            foreach ($snapshot as $item) {
                PurchaseItems::create([
                    'purchase_id' => $order->id,
                    'image_id' => $item['image_id'],
                    'source_cart_item_id' => $item['source_cart_item_id'],
                    'price' => $this->money->toDecimal($item['price_minor']),
                    'purchased_type' => $item['purchased_type'],
                    'snapshot' => $item['snapshot'],
                ]);
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'operation' => 'purchase',
                'method' => $method,
                'status' => 'pending',
                'amount_minor' => $totalMinor,
                'currency' => $order->currency,
                'idempotency_key' => $idempotencyKey,
                'paypal_request_id' => $method === 'paypal' ? 'create-purchase-'.$idempotencyKey : null,
            ]);

            return ['order' => $order->fresh('items'), 'payment' => $payment];
        }, 5);
    }

    private function buildSnapshot(int $userId, string $type, array $data): Collection
    {
        return match ($type) {
            'single_media' => $this->snapshotMedia([(int) $data['media_id']], null, 'single'),
            'multiple_media' => $this->snapshotMedia(
                collect($data['media_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
                null,
                'multiple',
            ),
            'collection' => $this->snapshotCollection((int) $data['collection_id'], null),
            'cart' => $this->snapshotCart($userId),
            default => throw new CommerceException('Unsupported order type.', 422),
        };
    }

    private function snapshotCart(int $userId): Collection
    {
        $cart = Cart::query()->where('user_id', $userId)->lockForUpdate()->first();
        if (! $cart) {
            throw new CommerceException('Cart not found.', 404);
        }

        $cartItems = CartItems::query()
            ->where('cart_id', $cart->id)
            ->orderByRaw("CASE WHEN type = 'collection' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($cartItems->isEmpty()) {
            throw new CommerceException('Cart is empty.', 422);
        }

        $seen = collect();
        $snapshot = collect();
        foreach ($cartItems as $cartItem) {
            $items = $cartItem->type === 'collection'
                ? $this->snapshotCollection((int) $cartItem->event_id, $cartItem->id)
                : $this->snapshotMedia([(int) $cartItem->image_id], $cartItem->id, 'single');

            foreach ($items as $item) {
                if ($seen->contains($item['image_id'])) {
                    continue;
                }
                $seen->push($item['image_id']);
                $snapshot->push($item);
            }
        }

        return $snapshot;
    }

    private function snapshotMedia(array $ids, ?int $cartItemId, string $purchasedType): Collection
    {
        $ids = collect($ids)->filter(fn ($id) => $id > 0)->unique()->values();
        $images = EventsImges::query()
            ->with('events:id,is_active')
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($ids->isEmpty() || $images->count() !== $ids->count()) {
            throw new CommerceException('One or more media items do not exist.', 422);
        }

        return $ids->map(function (int $id) use ($images, $cartItemId, $purchasedType) {
            $image = $images[$id];
            $priceMinor = $this->assertSaleable($image);

            return $this->snapshotRecord($image, $priceMinor, $cartItemId, $purchasedType);
        });
    }

    private function snapshotCollection(int $collectionId, ?int $cartItemId): Collection
    {
        $collection = Events::query()->lockForUpdate()->find($collectionId);
        if (! $collection || ! $this->isActive($collection->is_active)) {
            throw new CommerceException('Collection is not available for sale.', 422);
        }

        $images = EventsImges::query()
            ->where('event_id', $collectionId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($images->isEmpty()) {
            throw new CommerceException('Collection has no purchasable media.', 422);
        }

        $raw = $images->map(fn ($image) => $this->assertSaleable($image));
        $rawTotal = (int) $raw->sum();
        $discountedTotal = intdiv($rawTotal * (100 - self::COLLECTION_DISCOUNT_PERCENT) + 50, 100);
        $allocated = 0;

        return $images->values()->map(function ($image, int $index) use (
            $raw,
            $rawTotal,
            $discountedTotal,
            $cartItemId,
            &$allocated,
        ) {
            $isLast = $index === $raw->count() - 1;
            $priceMinor = $isLast
                ? $discountedTotal - $allocated
                : intdiv((int) $raw[$index] * $discountedTotal, $rawTotal);
            $allocated += $priceMinor;

            return $this->snapshotRecord($image, $priceMinor, $cartItemId, 'collection');
        });
    }

    private function snapshotRecord(EventsImges $image, int $priceMinor, ?int $cartItemId, string $type): array
    {
        return [
            'image_id' => $image->id,
            'source_cart_item_id' => $cartItemId,
            'price_minor' => $priceMinor,
            'purchased_type' => $type,
            'snapshot' => [
                'media_id' => $image->id,
                'event_id' => $image->event_id,
                'price_minor' => $priceMinor,
                'currency' => strtoupper((string) config('paypal.currency', 'USD')),
                'media_type' => $image->type,
            ],
        ];
    }

    private function assertSaleable(EventsImges $image): int
    {
        if (! $this->isActive($image->is_active) || ($image->relationLoaded('events') && ! $this->isActive($image->events?->is_active))) {
            throw new CommerceException("Media {$image->id} is not available for sale.", 422);
        }

        $priceMinor = $this->money->fromDecimal((string) $image->price);
        if ($priceMinor < 1) {
            throw new CommerceException("Media {$image->id} does not have a valid sale price.", 422);
        }

        return $priceMinor;
    }

    private function isActive(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'active'], true);
    }

    private function description(string $type): string
    {
        return match ($type) {
            'single_media' => 'Single media purchase',
            'collection' => 'Collection purchase',
            'multiple_media' => 'Multiple media purchase',
            default => 'Cart purchase',
        };
    }
}
