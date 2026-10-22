<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Models\CreditTransaction;
use App\Models\Entitlement;
use App\Models\EventsImges;
use App\Models\LicenceType;
use App\Models\Purchases;
use App\Models\User;
use App\Models\UserCreditGrant;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanQuotaService
{
    public function __construct(
        private readonly MediaTierService $tierService,
        private readonly WalletLedgerService $ledger,
        private readonly MinorMoney $money,
    ) {}

    /**
     * Get aggregated and tier-specific credit balances for a user.
     */
    public function getUserCredits(User $user): array
    {
        $grants = UserCreditGrant::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('remaining_photos', '>', 0)
                    ->orWhere('remaining_videos', '>', 0);
            })
            ->with('mediaTier')
            ->orderBy('tier_rank', 'asc')
            ->get();

        $totalPhotos = (int) $grants->sum('remaining_photos');
        $totalVideos = (int) $grants->sum('remaining_videos');

        // Group by tier
        $byTier = [];
        foreach ($grants as $grant) {
            $rank = $grant->tier_rank;
            if (! isset($byTier[$rank])) {
                $byTier[$rank] = [
                    'tier_id' => $grant->media_tier_id,
                    'tier_name' => $grant->tier_name,
                    'tier_rank' => $grant->tier_rank,
                    'remaining_photos' => 0,
                    'remaining_videos' => 0,
                    'max_eligible_photo_price' => (float) $grant->max_eligible_photo_price,
                    'max_eligible_video_price' => (float) $grant->max_eligible_video_price,
                ];
            }
            $byTier[$rank]['remaining_photos'] += $grant->remaining_photos;
            $byTier[$rank]['remaining_videos'] += $grant->remaining_videos;
        }

        return [
            'total_photos' => $totalPhotos,
            'total_videos' => $totalVideos,
            'by_tier' => array_values($byTier),
            'grants_count' => $grants->count(),
        ];
    }

    /**
     * Check if user would exceed accumulation limits when purchasing a plan.
     */
    public function checkAccumulationLimits(User $user, LicenceType $plan): void
    {
        $current = $this->getUserCredits($user);

        $maxPhotos = (int) ($plan->max_accumulated_photos ?: 1000);
        $maxVideos = (int) ($plan->max_accumulated_videos ?: 500);

        if ($current['total_photos'] + (int) $plan->photo_quota > $maxPhotos) {
            throw new CommerceException(
                "Purchasing this plan would exceed your maximum allowed photo accumulation limit ({$maxPhotos} photos). Current balance: {$current['total_photos']}.",
                422
            );
        }

        if ($current['total_videos'] + (int) $plan->video_quota > $maxVideos) {
            throw new CommerceException(
                "Purchasing this plan would exceed your maximum allowed video accumulation limit ({$maxVideos} videos). Current balance: {$current['total_videos']}.",
                422
            );
        }
    }

    /**
     * Grant permanent photo and video credits to a user from a plan.
     */
    public function grantCredits(
        User $user,
        LicenceType $plan,
        ?Purchases $order = null,
        string $paymentMethod = 'wallet',
        ?string $reference = null
    ): UserCreditGrant {
        $this->checkAccumulationLimits($user, $plan);

        $tier = $plan->mediaTier;
        $tierRank = $tier ? (int) $tier->rank : 1;
        $tierName = $tier ? $tier->name : ($plan->name ?: 'basic');
        $photoQuota = (int) $plan->photo_quota;
        $videoQuota = (int) $plan->video_quota;

        $maxPhotoPrice = $tier ? $tier->maxEligiblePhotoPrice() : 0.0;
        $maxVideoPrice = $tier ? $tier->maxEligibleVideoPrice() : 0.0;
        $payoutCapPhoto = $tier ? (float) $tier->payout_cap_photo : 0.0;
        $payoutCapVideo = $tier ? (float) $tier->payout_cap_video : 0.0;

        return DB::transaction(function () use (
            $user, $plan, $order, $tier, $tierRank, $tierName,
            $photoQuota, $videoQuota, $maxPhotoPrice, $maxVideoPrice,
            $payoutCapPhoto, $payoutCapVideo, $paymentMethod, $reference
        ) {
            $grant = UserCreditGrant::create([
                'user_id' => $user->id,
                'licence_id' => $plan->id,
                'purchase_id' => $order?->id,
                'media_tier_id' => $tier?->id,
                'tier_rank' => $tierRank,
                'tier_name' => $tierName,
                'photo_count' => $photoQuota,
                'video_count' => $videoQuota,
                'remaining_photos' => $photoQuota,
                'remaining_videos' => $videoQuota,
                'max_eligible_photo_price' => $maxPhotoPrice,
                'max_eligible_video_price' => $maxVideoPrice,
                'payout_cap_photo' => $payoutCapPhoto,
                'payout_cap_video' => $payoutCapVideo,
                'payment_method' => $paymentMethod,
                'payment_reference' => $reference ?: ($order?->idempotency_key ?: (string) Str::uuid()),
            ]);

            if ($photoQuota > 0) {
                CreditTransaction::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'grant_id' => $grant->id,
                    'media_id' => null,
                    'media_type' => 'photo',
                    'action' => 'grant',
                    'quantity' => $photoQuota,
                    'snapshot' => [
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->name,
                        'tier' => $tierName,
                        'payment_method' => $paymentMethod,
                    ],
                ]);
            }

            if ($videoQuota > 0) {
                CreditTransaction::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'grant_id' => $grant->id,
                    'media_id' => null,
                    'media_type' => 'video',
                    'action' => 'grant',
                    'quantity' => $videoQuota,
                    'snapshot' => [
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->name,
                        'tier' => $tierName,
                        'payment_method' => $paymentMethod,
                    ],
                ]);
            }

            return $grant;
        });
    }

    /**
     * Check if a user is eligible to redeem a media item with their credits.
     */
    public function checkRedemptionEligibility(User $user, EventsImges $media): array
    {
        $media->loadMissing('events.user', 'mediaTier');

        $isPublished = in_array($media->is_active, [true, 1, '1', 'true', 'active'], true)
            && (bool) $media->events?->is_active;

        if (! $isPublished) {
            return [
                'eligible' => false,
                'reason' => 'Media is not published or active.',
                'code' => 'unpublished',
            ];
        }

        // Creator cannot self-redeem
        if ((int) $media->events?->user_id === (int) $user->id) {
            return [
                'eligible' => false,
                'reason' => 'You cannot redeem your own content.',
                'code' => 'self_redemption',
            ];
        }

        // Check if already owned
        $alreadyOwned = Entitlement::where('user_id', $user->id)
            ->where('media_id', $media->id)
            ->exists();

        if ($alreadyOwned) {
            return [
                'eligible' => true,
                'already_owned' => true,
                'reason' => 'You already own this media.',
                'code' => 'already_owned',
            ];
        }

        $isVideo = in_array(strtolower((string) $media->type), ['video', 'videos', 'mp4'], true);
        $listingPrice = (float) $media->price;

        // Classify tier if not set
        $tier = $media->mediaTier ?: $this->tierService->classify($media->type ?: 'image', $listingPrice);

        if (! $tier || ! $tier->is_plan_eligible || ! $media->is_plan_redeemable) {
            return [
                'eligible' => false,
                'reason' => 'This media exceeds the plan redemption price limit and can only be purchased directly in Marketplace.',
                'code' => 'tier_exceeded',
            ];
        }

        // Find eligible grant: remaining > 0, tier_rank >= media_tier_rank
        $grantQuery = UserCreditGrant::query()
            ->where('user_id', $user->id)
            ->where('tier_rank', '>=', $tier->rank);

        if ($isVideo) {
            $grantQuery->where('remaining_videos', '>', 0);
        } else {
            $grantQuery->where('remaining_photos', '>', 0);
        }

        $eligibleGrant = $grantQuery->orderBy('tier_rank', 'asc')->orderBy('id', 'asc')->first();

        if (! $eligibleGrant) {
            // Check if user has ANY credits for this media type
            $hasAnyCredits = UserCreditGrant::query()
                ->where('user_id', $user->id)
                ->where($isVideo ? 'remaining_videos' : 'remaining_photos', '>', 0)
                ->exists();

            return [
                'eligible' => false,
                'required_tier' => $tier->name,
                'required_tier_rank' => $tier->rank,
                'has_credits' => $hasAnyCredits,
                'reason' => $hasAnyCredits
                    ? "This content requires a higher tier plan ({$tier->name} or higher). Please upgrade your plan."
                    : 'You do not have enough credits to redeem this media.',
                'code' => $hasAnyCredits ? 'higher_tier_required' : 'insufficient_credits',
            ];
        }

        return [
            'eligible' => true,
            'media_tier' => $tier->name,
            'media_tier_rank' => $tier->rank,
            'grant_id' => $eligibleGrant->id,
            'grant_tier' => $eligibleGrant->tier_name,
            'grant_tier_rank' => $eligibleGrant->tier_rank,
            'is_video' => $isVideo,
        ];
    }

    /**
     * Redeem a photo or video using user's plan credits.
     * Capped creator payout is atomically calculated and credited to creator's wallet.
     */
    public function redeemMedia(User $user, EventsImges $media): array
    {
        $eligibility = $this->checkRedemptionEligibility($user, $media);

        if (! $eligibility['eligible']) {
            throw new CommerceException($eligibility['reason'], 422);
        }

        if (! empty($eligibility['already_owned'])) {
            return [
                'status' => 'already_owned',
                'media_id' => $media->id,
                'message' => 'You already own this media.',
                'download_url' => route('media.download', ['media' => $media->id]),
                'remaining_photos' => $user->remainingPhotos(),
                'remaining_videos' => $user->remainingVideos(),
            ];
        }

        $isVideo = (bool) $eligibility['is_video'];
        $listingPrice = (float) $media->price;

        return DB::transaction(function () use ($user, $media, $isVideo, $listingPrice, $eligibility) {
            // Lock the lowest eligible grant for update
            $grant = UserCreditGrant::query()
                ->where('id', $eligibility['grant_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $quotaField = $isVideo ? 'remaining_videos' : 'remaining_photos';
            if ($grant->{$quotaField} < 1) {
                throw new CommerceException('Selected credit grant has no remaining quota.', 422);
            }

            // Deduct exactly one unit
            $grant->decrement($quotaField);

            // Calculate capped creator payout
            $payoutCap = $isVideo ? (float) $grant->payout_cap_video : (float) $grant->payout_cap_photo;
            $creatorPayout = round(min($listingPrice, $payoutCap), 2);
            $referencePriceDiff = round(max(0.0, $listingPrice - $creatorPayout), 2);

            // Create Entitlement
            $entitlement = Entitlement::create([
                'user_id' => $user->id,
                'media_id' => $media->id,
                'order_id' => $grant->purchase_id,
                'source' => 'plan_redemption',
                'metadata' => [
                    'grant_id' => $grant->id,
                    'grant_tier' => $grant->tier_name,
                    'media_tier' => $eligibility['media_tier'],
                    'media_type' => $isVideo ? 'video' : 'photo',
                    'listing_price' => $listingPrice,
                    'payout_cap' => $payoutCap,
                    'creator_payout' => $creatorPayout,
                    'reference_price_difference' => $referencePriceDiff,
                ],
                'granted_at' => now(),
            ]);

            // Credit Creator Wallet
            $seller = $media->events?->user;
            if ($seller && $creatorPayout > 0) {
                $sellerWallet = Wallet::query()->where('user_id', $seller->id)->lockForUpdate()->first();
                if (! $sellerWallet) {
                    $sellerWallet = Wallet::create([
                        'user_id' => $seller->id,
                        'amount' => '0.00',
                        'balance_minor' => 0,
                        'currency' => 'USD',
                    ]);
                    $sellerWallet = Wallet::query()->where('user_id', $seller->id)->lockForUpdate()->first();
                }

                // Reference dummy/grant order for ledger
                $order = $grant->purchase ?: Purchases::create([
                    'user_id' => $user->id,
                    'currency' => 'USD',
                    'amount' => '0.00',
                    'amount_minor' => 0,
                    'status' => 'completed',
                    'order_type' => 'plan_redemption',
                    'idempotency_key' => 'plan-redemption-order:' . $entitlement->id,
                ]);

                $payoutMinor = $this->money->fromDecimal((string) $creatorPayout);
                $this->ledger->post(
                    $sellerWallet,
                    $order,
                    null,
                    'credit',
                    'plan_media_redemption',
                    $payoutMinor,
                    'creator-plan-payout:' . $entitlement->id,
                    'grant:' . $grant->id . ':media:' . $media->id,
                    [
                        'media_id' => $media->id,
                        'entitlement_id' => $entitlement->id,
                        'creator_payout' => $creatorPayout,
                        'listing_price' => $listingPrice,
                        'payout_cap' => $payoutCap,
                        'reference_price_difference' => $referencePriceDiff,
                    ]
                );
            }

            // Record in Credit Transaction Ledger
            $tx = CreditTransaction::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'grant_id' => $grant->id,
                'media_id' => $media->id,
                'media_type' => $isVideo ? 'video' : 'photo',
                'action' => 'redemption',
                'quantity' => -1,
                'snapshot' => [
                    'media_id' => $media->id,
                    'media_title' => $media->events?->title,
                    'media_type' => $isVideo ? 'video' : 'photo',
                    'listing_price' => $listingPrice,
                    'media_tier' => $eligibility['media_tier'],
                    'grant_tier' => $grant->tier_name,
                    'payout_cap' => $payoutCap,
                    'creator_payout' => $creatorPayout,
                    'reference_price_difference' => $referencePriceDiff,
                    'creator_id' => $seller?->id,
                ],
            ]);

            return [
                'status' => 'success',
                'message' => 'Media redeemed successfully with plan credits.',
                'media_id' => $media->id,
                'download_url' => route('media.download', ['media' => $media->id]),
                'remaining_photos' => $user->remainingPhotos(),
                'remaining_videos' => $user->remainingVideos(),
                'creator_payout' => $creatorPayout,
                'reference_price_difference' => $referencePriceDiff,
                'transaction_id' => $tx->id,
            ];
        }, 5);
    }
}
