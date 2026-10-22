<?php

namespace App\Services;

use App\Models\MediaTier;
use Illuminate\Validation\ValidationException;

class PlanFinancialValidator
{
    /**
     * Static convenience validator for plan pricing & quotas.
     */
    public static function validatePlan(float $price, ?MediaTier $tier, int $photoQuota, int $videoQuota): array
    {
        $instance = app(self::class);
        $metrics = $instance->calculate($price, $tier?->id, $photoQuota, $videoQuota);

        return [
            'is_valid' => $metrics['is_valid'],
            'plan_price' => $metrics['plan_price'],
            'effective_plan_price' => $metrics['effective_plan_price'],
            'max_potential_catalog_value' => $metrics['max_potential_value'],
            'max_creator_liability' => $metrics['max_creator_liability'],
            'catalog_value_is_valid' => $metrics['catalog_value_is_valid'],
            'creator_liability_is_valid' => $metrics['creator_liability_is_valid'],
            'difference' => $metrics['catalog_price_headroom'],
            'catalog_price_headroom' => $metrics['catalog_price_headroom'],
            'creator_liability_headroom' => $metrics['creator_liability_headroom'],
            'message' => $metrics['is_valid']
                ? 'Plan meets financial validation standards.'
                : implode(' ', $metrics['errors']),
            'metrics' => $metrics,
        ];
    }

    /**
     * Compute financial metrics for a Plan configuration.
     *
     * @param  float  $price  Selling price of the plan
     * @param  int|null  $tierId  Associated media_tier_id
     * @param  int  $photoQuota  Number of photos included
     * @param  int  $videoQuota  Number of videos included
     */
    public function calculate(float $price, ?int $tierId, int $photoQuota, int $videoQuota): array
    {
        $tier = $tierId ? MediaTier::find($tierId) : null;

        $maxEligiblePhotoPrice = $tier ? $tier->maxEligiblePhotoPrice() : 0.0;
        $maxEligibleVideoPrice = $tier ? $tier->maxEligibleVideoPrice() : 0.0;

        $photoCatalogValue = round($photoQuota * $maxEligiblePhotoPrice, 2);
        $videoCatalogValue = round($videoQuota * $maxEligibleVideoPrice, 2);
        $maxPotentialValue = round($photoCatalogValue + $videoCatalogValue, 2);

        $photoCap = $tier ? (float) $tier->payout_cap_photo : 0.0;
        $videoCap = $tier ? (float) $tier->payout_cap_video : 0.0;
        $maxCreatorLiability = round(($photoQuota * $photoCap) + ($videoQuota * $videoCap), 2);

        // There are currently no plan discounts, so the configured selling price
        // is also the effective price collected for this validation.
        $effectivePlanPrice = round($price, 2);
        $catalogPriceHeadroom = round($effectivePlanPrice - $maxPotentialValue, 2);
        $creatorLiabilityHeadroom = round($effectivePlanPrice - $maxCreatorLiability, 2);
        $catalogValueIsValid = $maxPotentialValue <= $effectivePlanPrice;
        $creatorLiabilityIsValid = $maxCreatorLiability <= $effectivePlanPrice;

        // Listing-value coverage and capped creator liability are independent
        // requirements. Free plans also cannot include paid media quotas.
        $isValid = true;
        $reasons = [];

        if (($photoQuota > 0 || $videoQuota > 0) && ! $tier) {
            $isValid = false;
            $reasons[] = 'A media access tier must be selected when photo or video quotas are included.';
        }

        if (! $catalogValueIsValid) {
            $isValid = false;
            $reasons[] = "The maximum eligible listing value (\${$maxPotentialValue}) exceeds the effective plan price (\${$effectivePlanPrice}).";
        }

        if (! $creatorLiabilityIsValid) {
            $isValid = false;
            $reasons[] = "The maximum creator payout liability (\${$maxCreatorLiability}) exceeds the effective plan price (\${$effectivePlanPrice}).";
        }

        if ($price <= 0 && ($photoQuota > 0 || $videoQuota > 0)) {
            $isValid = false;
            $reasons[] = 'Free plans cannot include paid media quotas.';
        }

        return [
            'plan_price' => $price,
            'effective_plan_price' => $effectivePlanPrice,
            'effective_selling_price' => $effectivePlanPrice,
            'tier_id' => $tierId,
            'tier_name' => $tier?->name,
            'tier_rank' => $tier?->rank,
            'photo_quota' => $photoQuota,
            'video_quota' => $videoQuota,
            'max_eligible_photo_price' => $maxEligiblePhotoPrice,
            'max_eligible_video_price' => $maxEligibleVideoPrice,
            'maximum_eligible_photo_listing_price' => $maxEligiblePhotoPrice,
            'maximum_eligible_video_listing_price' => $maxEligibleVideoPrice,
            'photo_catalog_value' => $photoCatalogValue,
            'video_catalog_value' => $videoCatalogValue,
            'max_potential_value' => $maxPotentialValue,
            'payout_cap_photo' => $photoCap,
            'payout_cap_video' => $videoCap,
            'max_creator_liability' => $maxCreatorLiability,
            'catalog_value_is_valid' => $catalogValueIsValid,
            'creator_liability_is_valid' => $creatorLiabilityIsValid,
            'catalog_price_headroom' => $catalogPriceHeadroom,
            'creator_liability_headroom' => $creatorLiabilityHeadroom,
            'estimated_margin' => $creatorLiabilityHeadroom,
            'is_valid' => $isValid,
            'errors' => $reasons,
        ];
    }

    /**
     * Validate and assert financial safety for a plan before creation or update.
     *
     * @throws ValidationException
     */
    public function assertValid(float $price, ?int $tierId, int $photoQuota, int $videoQuota): array
    {
        $metrics = $this->calculate($price, $tierId, $photoQuota, $videoQuota);

        if (! $metrics['is_valid']) {
            throw ValidationException::withMessages([
                'financial_validation' => $metrics['errors'],
            ]);
        }

        return $metrics;
    }
}
