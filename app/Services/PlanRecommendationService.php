<?php

namespace App\Services;

use App\Models\MediaTier;

class PlanRecommendationService
{
    public const CUSTOMER_VALUE_TARGET_BPS = 11000;

    public function __construct(
        private readonly PlanFinancialEngine $engine,
        private readonly MinorMoney $money,
    ) {}

    public function recommend(string|float|int $price): array
    {
        $priceMinor = $this->money->fromDecimal((string) $price);
        $configuration = $this->engine->configurationStatus();
        if (! $configuration['is_complete']) {
            return [
                'is_available' => false,
                'reason' => 'Financial assumptions must be configured before quotas can be recommended.',
                'missing_assumptions' => $configuration['missing'],
            ];
        }

        if ($priceMinor <= 0) {
            return [
                'is_available' => false,
                'reason' => 'Enter a positive plan price.',
                'missing_assumptions' => [],
            ];
        }

        $settings = $configuration['settings'];
        $minimumCustomerValueMinor = intdiv(
            ($priceMinor * self::CUSTOMER_VALUE_TARGET_BPS) + 9999,
            10000,
        );
        $tiers = MediaTier::query()
            ->where('is_active', true)
            ->where('is_plan_eligible', true)
            ->orderBy('rank')
            ->get();
        $distribution = collect($settings->tier_distribution ?? []);
        $candidates = [];
        $basicTier = $tiers->first();

        foreach ($tiers as $tier) {
            $tierWeight = (int) ($distribution[(string) $tier->id] ?? 0);
            foreach (['photo', 'video'] as $mediaType) {
                $mediaWeight = $mediaType === 'photo'
                    ? (int) $settings->photo_allocation_bps
                    : 10000 - (int) $settings->photo_allocation_bps;
                $weight = $tierWeight * $mediaWeight;
                if ($weight > 0) {
                    $candidates[] = [
                        'tier_id' => (int) $tier->id,
                        'media_type' => $mediaType,
                        'weight' => $weight,
                        'count' => 0,
                        'blocked' => false,
                    ];
                }
            }
        }

        $photoQuota = 0;
        $videoQuota = 0;
        $limits = $this->buildLimits($tiers, $candidates, $basicTier?->id, $photoQuota, $videoQuota);
        $analysis = $this->engine->analyze(
            $priceMinor,
            0,
            0,
            $limits,
            $settings,
            $minimumCustomerValueMinor,
            self::CUSTOMER_VALUE_TARGET_BPS,
        );

        // Weighted fair allocation. A candidate that cannot be added at the current
        // price can never become affordable later, so it is safely removed.
        for ($units = 0; $units < 100000; $units++) {
            $available = collect($candidates)->filter(fn ($candidate) => ! $candidate['blocked']);
            if ($available->isEmpty()) {
                break;
            }

            $index = $available->keys()->sortBy(function ($index) use ($candidates) {
                $candidate = $candidates[$index];

                return ($candidate['count'] + 1) / $candidate['weight'];
            })->first();
            $candidate = $candidates[$index];
            $nextPhotos = $photoQuota + ($candidate['media_type'] === 'photo' ? 1 : 0);
            $nextVideos = $videoQuota + ($candidate['media_type'] === 'video' ? 1 : 0);
            $candidates[$index]['count']++;
            $nextLimits = $this->buildLimits(
                $tiers,
                $candidates,
                $basicTier?->id,
                $nextPhotos,
                $nextVideos,
            );
            $next = $this->engine->analyze(
                $priceMinor,
                $nextPhotos,
                $nextVideos,
                $nextLimits,
                $settings,
                $minimumCustomerValueMinor,
                self::CUSTOMER_VALUE_TARGET_BPS,
            );

            if ($next['profit_requirement_is_valid']) {
                $photoQuota = $nextPhotos;
                $videoQuota = $nextVideos;
                $limits = $nextLimits;
                $analysis = $next;
            } else {
                $candidates[$index]['count']--;
                $candidates[$index]['blocked'] = true;
            }
        }

        if ($photoQuota + $videoQuota === 0 || ! $analysis['is_valid']) {
            return [
                'is_available' => false,
                'reason' => sprintf(
                    'No recommendation can satisfy both the configured platform profit requirement and the 10%% customer-value target. The highest profit-safe potential individual Cart value is %s; the required target is %s.',
                    $this->money->toDecimal((int) $analysis['max_catalog_value_minor']),
                    $this->money->toDecimal($minimumCustomerValueMinor),
                ),
                'failure_code' => 'profit_and_customer_value_incompatible',
                'missing_assumptions' => [],
                'analysis' => $analysis,
            ];
        }

        $recommendedLimits = collect($limits)
            ->filter(fn ($limit) => $limit['photo_maximum'] > 0 || $limit['video_maximum'] > 0)
            ->values()
            ->all();
        $recommendedTierIds = collect($recommendedLimits)->pluck('media_tier_id')->all();
        $highestTierId = $tiers->whereIn('id', $recommendedTierIds)->sortByDesc('rank')->first()?->id;

        return [
            'is_available' => true,
            'price' => $this->money->toDecimal($priceMinor),
            'photo_quota' => $photoQuota,
            'video_quota' => $videoQuota,
            'media_tier_id' => $highestTierId,
            'tier_limits' => $recommendedLimits,
            'analysis' => $analysis,
            'potential_cart_value_minor' => $analysis['max_catalog_value_minor'],
            'minimum_customer_value_minor' => $minimumCustomerValueMinor,
            'potential_cart_value_ratio_bps' => $analysis['potential_cart_value_ratio_bps'],
            'potential_value_is_not_guaranteed_savings' => true,
        ];
    }

    private function buildLimits($tiers, array $candidates, ?int $basicTierId, int $photoQuota, int $videoQuota): array
    {
        $counts = collect($candidates)->groupBy('tier_id');

        return $tiers->map(function ($tier) use ($counts, $basicTierId, $photoQuota, $videoQuota) {
            $tierCounts = $counts->get((int) $tier->id, collect());
            $photoMaximum = (int) ($tierCounts->firstWhere('media_type', 'photo')['count'] ?? 0);
            $videoMaximum = (int) ($tierCounts->firstWhere('media_type', 'video')['count'] ?? 0);

            if ((int) $tier->id === $basicTierId) {
                $photoMaximum = $photoQuota;
                $videoMaximum = $videoQuota;
            }

            return [
                'media_tier_id' => (int) $tier->id,
                'photo_maximum' => $photoMaximum,
                'video_maximum' => $videoMaximum,
            ];
        })->all();
    }
}
