<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Models\Entitlement;
use App\Models\EventsImges;
use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\User;
use App\Models\UserCreditGrant;

class PlanSavingsService
{
    public function __construct(
        private readonly CheckoutCartSnapshot $cartPricing,
        private readonly MediaTierService $tierService,
        private readonly MinorMoney $money,
        private readonly PlanFinancialEngine $financialEngine,
    ) {}

    /**
     * Return an evidence-backed comparison for every plan grant owned by a user.
     */
    public function forUser(User $user): array
    {
        $grants = UserCreditGrant::query()
            ->where('user_id', $user->id)
            ->with([
                'plan:id,name,slug',
                'purchase:id,status,amount_minor,currency,payment_method,paid_at',
                'mediaTier:id,name,rank',
                'transactions' => fn ($query) => $query
                    ->where('action', 'redemption')
                    ->orderBy('id'),
            ])
            ->latest('id')
            ->get();

        $tiers = MediaTier::query()
            ->where('is_active', true)
            ->where('is_plan_eligible', true)
            ->orderBy('rank')
            ->get(['id', 'name', 'rank']);

        $plans = $grants->map(fn (UserCreditGrant $grant) => $this->grantComparison($grant, $tiers))->values();

        return [
            'plans' => $plans->all(),
            'plans_count' => $plans->count(),
            'comparisons_complete' => $plans->every(fn (array $plan) => $plan['comparison']['is_complete']),
        ];
    }

    /**
     * Estimate a plan against the user's current cart snapshot. This is an
     * estimate only; the paid grant and each redemption receive new immutable
     * snapshots when the corresponding transaction succeeds.
     */
    public function estimateForCart(User $user, LicenceType $plan): array
    {
        try {
            $cartItems = $this->cartPricing->quoteCart((int) $user->id);
        } catch (CommerceException) {
            return $this->unavailableEstimate('cart_unavailable');
        }

        $ownedIds = Entitlement::query()
            ->where('user_id', $user->id)
            ->whereIn('media_id', $cartItems->pluck('image_id'))
            ->pluck('media_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $quotedItems = $cartItems
            ->reject(fn (array $item) => in_array((int) $item['image_id'], $ownedIds, true))
            ->values();

        if ($quotedItems->isEmpty()) {
            return $this->unavailableEstimate('cart_unavailable');
        }

        $media = EventsImges::query()
            ->with('mediaTier:id,name,rank,is_plan_eligible')
            ->whereIn('id', $quotedItems->pluck('image_id'))
            ->get()
            ->keyBy('id');

        $planLimits = collect($this->financialEngine->limitsForPlan($plan))->keyBy('media_tier_id');
        $tierUsage = [];
        $photosLeft = (int) $plan->photo_quota;
        $videosLeft = (int) $plan->video_quota;
        $matchedValueMinor = 0;
        $matchedPhotos = 0;
        $matchedVideos = 0;

        foreach ($quotedItems as $item) {
            $mediaItem = $media->get((int) $item['image_id']);
            if (! $mediaItem || ! $mediaItem->is_plan_redeemable) {
                continue;
            }

            $itemTier = $mediaItem->mediaTier
                ?: $this->tierService->classify((string) $mediaItem->type, (float) $mediaItem->price);
            $limit = $itemTier ? $planLimits->get((int) $itemTier->id) : null;
            if (! $itemTier || ! $itemTier->is_plan_eligible || ! $limit) {
                continue;
            }

            $isVideo = in_array(strtolower((string) $mediaItem->type), ['video', 'videos', 'mp4'], true);
            if ($isVideo) {
                $used = (int) ($tierUsage[$itemTier->id]['videos'] ?? 0);
                if ($videosLeft < 1 || $used >= (int) $limit['video_maximum']) {
                    continue;
                }
                $videosLeft--;
                $tierUsage[$itemTier->id]['videos'] = $used + 1;
                $matchedVideos++;
            } else {
                $used = (int) ($tierUsage[$itemTier->id]['photos'] ?? 0);
                if ($photosLeft < 1 || $used >= (int) $limit['photo_maximum']) {
                    continue;
                }
                $photosLeft--;
                $tierUsage[$itemTier->id]['photos'] = $used + 1;
                $matchedPhotos++;
            }

            $matchedValueMinor += (int) $item['price_minor'];
        }

        $currency = strtoupper((string) config('paypal.currency', 'USD'));
        $planPriceMinor = $this->money->fromDecimal((string) $plan->price);
        $differenceMinor = $matchedValueMinor - $planPriceMinor;

        return [
            'available' => true,
            'reason' => null,
            'currency' => $currency,
            'plan_price_minor' => $planPriceMinor,
            'cart_total_minor' => (int) $quotedItems->sum('price_minor'),
            'comparable_cart_value_minor' => $matchedValueMinor,
            'difference_minor' => $differenceMinor,
            'comparison_status' => $this->comparisonStatus($differenceMinor),
            'matched_items' => $matchedPhotos + $matchedVideos,
            'matched_photos' => $matchedPhotos,
            'matched_videos' => $matchedVideos,
            'cart_items' => $quotedItems->count(),
            'pricing_snapshot' => [
                'includes_collection_discounts' => true,
                'tax_minor' => 0,
            ],
        ];
    }

    private function grantComparison(UserCreditGrant $grant, $tiers): array
    {
        $redemptions = $grant->transactions;
        $paidMinor = $grant->plan_price_paid_minor;
        $currency = $grant->currency ? strtoupper((string) $grant->currency) : null;
        $paidSource = 'grant_snapshot';

        if ($paidMinor === null && $grant->purchase && $grant->purchase->amount_minor !== null) {
            $paidMinor = (int) $grant->purchase->amount_minor;
            $currency = strtoupper((string) $grant->purchase->currency);
            $paidSource = 'purchase_record';
        }

        $tracked = $redemptions->filter(fn ($tx) => $tx->cart_value_minor !== null);
        $missingSnapshots = $redemptions->count() - $tracked->count();
        $redemptionCurrencies = $tracked->pluck('currency')
            ->filter()
            ->map(fn ($value) => strtoupper((string) $value))
            ->unique()
            ->values();
        $currencyMatches = $redemptionCurrencies->isEmpty()
            || ($currency !== null && $redemptionCurrencies->count() === 1 && $redemptionCurrencies->first() === $currency);
        $isComplete = $paidMinor !== null && $missingSnapshots === 0 && $currencyMatches;
        $cartValueMinor = (int) $tracked->sum('cart_value_minor');
        $differenceMinor = $isComplete ? $cartValueMinor - (int) $paidMinor : null;
        $unusedCredits = (int) $grant->remaining_photos + (int) $grant->remaining_videos;
        $tierLimits = collect($grant->tier_limits_snapshot ?? []);
        $tierUsage = collect($grant->tier_usage_snapshot ?? [])->keyBy(fn ($row) => (string) ($row['media_tier_id'] ?? ''));
        $tierAllowances = $tierLimits->map(function (array $limit) use ($grant, $tierUsage) {
            $used = $tierUsage->get((string) ($limit['media_tier_id'] ?? ''), []);

            return [
                'id' => (int) $limit['media_tier_id'],
                'name' => $limit['tier_name'] ?? null,
                'rank' => (int) ($limit['tier_rank'] ?? 0),
                'photo_maximum' => (int) ($limit['photo_maximum'] ?? 0),
                'video_maximum' => (int) ($limit['video_maximum'] ?? 0),
                'photos_used' => (int) ($used['photos'] ?? 0),
                'videos_used' => (int) ($used['videos'] ?? 0),
                'remaining_photos' => min((int) $grant->remaining_photos, max(0, (int) ($limit['photo_maximum'] ?? 0) - (int) ($used['photos'] ?? 0))),
                'remaining_videos' => min((int) $grant->remaining_videos, max(0, (int) ($limit['video_maximum'] ?? 0) - (int) ($used['videos'] ?? 0))),
            ];
        })->values();

        return [
            'id' => $grant->id,
            'purchase_id' => $grant->purchase_id,
            'plan' => $grant->plan ? [
                'id' => $grant->plan->id,
                'name' => $grant->plan->name,
                'slug' => $grant->plan->slug,
            ] : null,
            'tier_name' => $grant->tier_name,
            'tier_rank' => (int) $grant->tier_rank,
            'photo_count' => (int) $grant->photo_count,
            'video_count' => (int) $grant->video_count,
            'remaining_photos' => (int) $grant->remaining_photos,
            'remaining_videos' => (int) $grant->remaining_videos,
            'payout_cap_photo' => (string) $grant->payout_cap_photo,
            'payout_cap_video' => (string) $grant->payout_cap_video,
            'payment_method' => $grant->payment_method,
            'created_at' => $grant->created_at,
            'eligible_tiers' => $tierAllowances->isNotEmpty()
                ? $tierAllowances->all()
                : $tiers->where('rank', '<=', (int) $grant->tier_rank)->map(fn (MediaTier $tier) => [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'rank' => (int) $tier->rank,
                    'remaining_photos' => (int) $grant->remaining_photos,
                    'remaining_videos' => (int) $grant->remaining_videos,
                ])->values()->all(),
            'comparison' => [
                'is_complete' => $isComplete,
                'incomplete_reason' => $isComplete ? null : $this->incompleteReason($paidMinor, $missingSnapshots, $currencyMatches),
                'currency' => $currency,
                'plan_price_paid_minor' => $paidMinor === null ? null : (int) $paidMinor,
                'plan_price_source' => $paidSource,
                'redeemed_cart_value_minor' => $cartValueMinor,
                'difference_minor' => $differenceMinor,
                'comparison_status' => $differenceMinor === null ? 'unavailable' : $this->comparisonStatus($differenceMinor),
                'redeemed_items' => $redemptions->count(),
                'tracked_redeemed_items' => $tracked->count(),
                'missing_price_snapshots' => $missingSnapshots,
                'unused_credits' => $unusedCredits,
                'has_unused_credits' => $unusedCredits > 0,
            ],
        ];
    }

    private function incompleteReason(mixed $paidMinor, int $missingSnapshots, bool $currencyMatches): string
    {
        if ($paidMinor === null) {
            return 'missing_plan_price';
        }
        if ($missingSnapshots > 0) {
            return 'historical_prices_unavailable';
        }

        return $currencyMatches ? 'unavailable' : 'currency_mismatch';
    }

    private function comparisonStatus(int $differenceMinor): string
    {
        return $differenceMinor > 0 ? 'savings' : ($differenceMinor < 0 ? 'extra_spending' : 'break_even');
    }

    private function unavailableEstimate(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
        ];
    }
}
