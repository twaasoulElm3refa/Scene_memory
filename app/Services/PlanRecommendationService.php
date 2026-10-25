<?php

namespace App\Services;

use App\Models\MediaTier;

class PlanRecommendationService
{
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
        $tiers = MediaTier::query()
            ->where('is_active', true)
            ->where('is_plan_eligible', true)
            ->orderBy('rank')
            ->get();
        $distribution = collect($settings->tier_distribution ?? []);
        $candidates = [];

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

        $limits = $tiers->map(fn ($tier) => [
            'media_tier_id' => (int) $tier->id,
            'photo_maximum' => 0,
            'video_maximum' => 0,
        ])->keyBy('media_tier_id')->all();
        $photoQuota = 0;
        $videoQuota = 0;
        $analysis = $this->engine->analyze($priceMinor, 0, 0, array_values($limits), $settings);

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
            $key = $candidate['media_type'].'_maximum';
            $limits[$candidate['tier_id']][$key]++;
            $nextPhotos = $photoQuota + ($candidate['media_type'] === 'photo' ? 1 : 0);
            $nextVideos = $videoQuota + ($candidate['media_type'] === 'video' ? 1 : 0);
            $next = $this->engine->analyze(
                $priceMinor,
                $nextPhotos,
                $nextVideos,
                array_values($limits),
                $settings,
            );

            if ($next['is_valid']) {
                $photoQuota = $nextPhotos;
                $videoQuota = $nextVideos;
                $candidates[$index]['count']++;
                $analysis = $next;
            } else {
                $limits[$candidate['tier_id']][$key]--;
                $candidates[$index]['blocked'] = true;
            }
        }

        if ($photoQuota + $videoQuota === 0) {
            return [
                'is_available' => false,
                'reason' => 'The entered price is below the minimum sustainable price for one media credit.',
                'missing_assumptions' => [],
                'analysis' => $analysis,
            ];
        }

        $recommendedLimits = collect($limits)->values()
            ->filter(fn ($limit) => $limit['photo_maximum'] > 0 || $limit['video_maximum'] > 0)
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
        ];
    }
}
