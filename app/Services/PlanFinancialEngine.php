<?php

namespace App\Services;

use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\PlanFinancialSetting;
use Illuminate\Support\Collection;

class PlanFinancialEngine
{
    public const REQUIRED_PAYMENT_METHODS = ['wallet', 'paypal'];

    public function __construct(private readonly MinorMoney $money) {}

    public function activeSettings(): ?PlanFinancialSetting
    {
        return PlanFinancialSetting::query()
            ->where('is_active', true)
            ->with('paymentFees')
            ->latest('version')
            ->first();
    }

    public function configurationStatus(?PlanFinancialSetting $settings = null): array
    {
        $settings ??= $this->activeSettings();
        $missing = [];

        if (! $settings) {
            return [
                'is_complete' => false,
                'missing' => ['active_financial_settings'],
                'settings' => null,
            ];
        }

        foreach ([
            'currency',
            'minimum_profit_minor',
            'minimum_margin_bps',
            'operating_cost_bps',
            'fixed_operating_cost_minor',
            'risk_reserve_bps',
            'tax_bps',
            'photo_allocation_bps',
            'tier_distribution',
        ] as $field) {
            if ($settings->{$field} === null || $settings->{$field} === '') {
                $missing[] = $field;
            }
        }
        if ((int) $settings->minimum_profit_minor <= 0 && (int) $settings->minimum_margin_bps <= 0) {
            $missing[] = 'positive_profit_requirement';
        }

        $fees = $settings->paymentFees->keyBy('payment_method');
        foreach (self::REQUIRED_PAYMENT_METHODS as $method) {
            $rule = $fees->get($method);
            if (! $rule || ! $rule->is_enabled) {
                $missing[] = "payment_fee.{$method}";

                continue;
            }
            if ($rule->percentage_bps === null) {
                $missing[] = "payment_fee.{$method}.percentage_bps";
            }
            if ($rule->fixed_fee_minor === null) {
                $missing[] = "payment_fee.{$method}.fixed_fee_minor";
            }
        }

        $tiers = MediaTier::query()
            ->where('is_active', true)
            ->where('is_plan_eligible', true)
            ->orderBy('rank')
            ->get();
        $distribution = collect($settings->tier_distribution ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (int) $value]);

        foreach ($tiers as $tier) {
            if (! $distribution->has((string) $tier->id)) {
                $missing[] = "tier_distribution.{$tier->id}";
            }
        }
        if ($tiers->isEmpty()) {
            $missing[] = 'eligible_media_tiers';
        } elseif ($distribution->only($tiers->pluck('id')->map(fn ($id) => (string) $id))->sum() !== 10000) {
            $missing[] = 'tier_distribution.total_must_equal_10000_bps';
        }

        return [
            'is_complete' => $missing === [],
            'missing' => array_values(array_unique($missing)),
            'settings' => $settings,
        ];
    }

    /**
     * @param  array<int, array<string, int>>  $tierLimits
     */
    public function analyze(
        int $priceMinor,
        int $photoQuota,
        int $videoQuota,
        array $tierLimits,
        ?PlanFinancialSetting $settings = null,
        ?int $minimumCustomerValueMinor = null,
        ?int $customerValueTargetBps = null,
    ): array {
        $configuration = $this->configurationStatus($settings);
        $settings = $configuration['settings'];
        $normalized = $this->hydrateTierLimits($tierLimits);
        $errors = [];

        if ($priceMinor < 0 || $photoQuota < 0 || $videoQuota < 0) {
            $errors[] = 'Price and quotas must not be negative.';
        }
        if (($photoQuota > 0 || $videoQuota > 0) && $normalized->isEmpty()) {
            $errors[] = 'At least one eligible media tier ceiling is required.';
        }

        foreach ($normalized as $limit) {
            if ($limit['photo_maximum'] > $photoQuota || $limit['video_maximum'] > $videoQuota) {
                $errors[] = "Tier {$limit['tier_name']} has a ceiling greater than the shared quota.";
            }
        }
        if ($photoQuota > 0 && $normalized->sum('photo_maximum') < $photoQuota) {
            $errors[] = 'Photo tier ceilings cannot consume the full shared photo quota.';
        }
        if ($videoQuota > 0 && $normalized->sum('video_maximum') < $videoQuota) {
            $errors[] = 'Video tier ceilings cannot consume the full shared video quota.';
        }

        $photoLiability = $this->maximumExposure($photoQuota, $normalized, 'photo_maximum', 'payout_cap_photo_minor');
        $videoLiability = $this->maximumExposure($videoQuota, $normalized, 'video_maximum', 'payout_cap_video_minor');
        $creatorLiability = $photoLiability + $videoLiability;
        $photoCatalog = $this->maximumExposure($photoQuota, $normalized, 'photo_maximum', 'max_price_photo_minor');
        $videoCatalog = $this->maximumExposure($videoQuota, $normalized, 'video_maximum', 'max_price_video_minor');
        $catalogValue = $photoCatalog + $videoCatalog;

        if (! $configuration['is_complete']) {
            $errors[] = 'Financial assumptions are incomplete: '.implode(', ', $configuration['missing']).'.';
        }

        $costs = $settings && $configuration['is_complete']
            ? $this->costsAtPrice($priceMinor, $settings)
            : $this->unknownCosts();
        $netRevenue = $costs['total_minor'] === null ? null : $priceMinor - $costs['total_minor'];
        $worstProfit = $netRevenue === null ? null : $netRevenue - $creatorLiability;
        $marginBps = $worstProfit === null || $priceMinor <= 0
            ? null
            : $this->signedRatioBps($worstProfit, $priceMinor);
        $requiredProfit = $settings && $configuration['is_complete']
            ? max(
                (int) $settings->minimum_profit_minor,
                $this->percentageCost($priceMinor, (int) $settings->minimum_margin_bps),
            )
            : null;
        $catalogValid = $minimumCustomerValueMinor === null
            ? $catalogValue <= $priceMinor
            : $catalogValue >= $minimumCustomerValueMinor;
        $profitValid = $worstProfit !== null && $requiredProfit !== null && $worstProfit >= $requiredProfit;

        if (! $catalogValid) {
            $errors[] = $minimumCustomerValueMinor === null
                ? 'Maximum eligible Cart value exceeds the plan selling price by '
                    .$this->money->toDecimal($catalogValue - $priceMinor).'.'
                : 'Maximum potential individual Cart value is below the recommendation target by '
                    .$this->money->toDecimal($minimumCustomerValueMinor - $catalogValue).'.';
        }
        if ($configuration['is_complete'] && ! $profitValid) {
            $shortfall = max(0, (int) $requiredProfit - (int) $worstProfit);
            $errors[] = 'Worst-case profit is below the configured requirement by '
                .$this->money->toDecimal($shortfall).'.';
        }
        if ($priceMinor === 0 && ($photoQuota > 0 || $videoQuota > 0)) {
            $errors[] = 'Free plans cannot include paid media quotas.';
        }

        $minimumPrice = $settings && $configuration['is_complete']
            ? $this->minimumSustainablePrice($creatorLiability, $catalogValue, $settings)
            : null;
        $isValid = $errors === [];

        return [
            'is_valid' => $isValid,
            'financial_safety_status' => $isValid ? 'safe' : ($configuration['is_complete'] ? 'unsafe' : 'unconfigured'),
            'configuration_complete' => $configuration['is_complete'],
            'missing_assumptions' => $configuration['missing'],
            'settings_version' => $settings?->version,
            'currency' => $settings?->currency,
            'plan_price_minor' => $priceMinor,
            'photo_quota' => $photoQuota,
            'video_quota' => $videoQuota,
            'tier_limits' => $normalized->values()->all(),
            'photo_creator_liability_minor' => $photoLiability,
            'video_creator_liability_minor' => $videoLiability,
            'max_creator_liability_minor' => $creatorLiability,
            'max_catalog_value_minor' => $catalogValue,
            'minimum_customer_value_minor' => $minimumCustomerValueMinor,
            'customer_value_target_bps' => $customerValueTargetBps,
            'potential_cart_value_ratio_bps' => $priceMinor > 0
                ? $this->signedRatioBps($catalogValue, $priceMinor)
                : null,
            'payment_processing_cost_minor' => $costs['payment_processing_minor'],
            'payment_fee_method' => $costs['payment_method'],
            'tax_cost_minor' => $costs['tax_minor'],
            'operating_cost_minor' => $costs['operating_minor'],
            'risk_reserve_minor' => $costs['risk_minor'],
            'total_non_payout_cost_minor' => $costs['total_minor'],
            'net_plan_revenue_minor' => $netRevenue,
            'required_profit_minor' => $requiredProfit,
            'worst_case_profit_minor' => $worstProfit,
            'worst_case_margin_bps' => $marginBps,
            'minimum_sustainable_price_minor' => $minimumPrice,
            'catalog_value_is_valid' => $catalogValid,
            'creator_liability_is_valid' => $creatorLiability <= $priceMinor,
            'profit_requirement_is_valid' => $profitValid,
            'errors' => array_values(array_unique($errors)),
            'plan_price' => $priceMinor / 100,
            'effective_plan_price' => $priceMinor / 100,
            'effective_selling_price' => $priceMinor / 100,
            'max_potential_value' => $catalogValue / 100,
            'max_creator_liability' => $creatorLiability / 100,
            'catalog_price_headroom' => ($priceMinor - $catalogValue) / 100,
            'creator_liability_headroom' => ($priceMinor - $creatorLiability) / 100,
            'worst_case_profit' => $worstProfit === null ? null : $worstProfit / 100,
            'worst_case_margin_percent' => $marginBps === null ? null : $marginBps / 100,
            'minimum_sustainable_price' => $minimumPrice === null ? null : $minimumPrice / 100,
        ];
    }

    /**
     * The largest worst-case creator liability a plan at this price can carry
     * while still meeting the configured profit requirement. It uses exactly the
     * same cost and requirement calculations as analyze().
     */
    public function profitBudget(int $priceMinor, PlanFinancialSetting $settings): array
    {
        $costs = $this->costsAtPrice($priceMinor, $settings);
        $requiredProfit = max(
            (int) $settings->minimum_profit_minor,
            $this->percentageCost($priceMinor, (int) $settings->minimum_margin_bps),
        );
        $netRevenue = $priceMinor - $costs['total_minor'];

        return [
            'plan_price_minor' => $priceMinor,
            'payment_processing_cost_minor' => $costs['payment_processing_minor'],
            'payment_fee_method' => $costs['payment_method'],
            'tax_cost_minor' => $costs['tax_minor'],
            'operating_cost_minor' => $costs['operating_minor'],
            'risk_reserve_minor' => $costs['risk_minor'],
            'total_non_payout_cost_minor' => $costs['total_minor'],
            'net_plan_revenue_minor' => $netRevenue,
            'required_profit_minor' => $requiredProfit,
            'liability_budget_minor' => $netRevenue - $requiredProfit,
        ];
    }

    public function legacyTierLimits(?int $tierId, int $photoQuota, int $videoQuota): array
    {
        if (! $tierId) {
            return [];
        }
        $selected = MediaTier::find($tierId);
        if (! $selected) {
            return [];
        }

        return MediaTier::query()
            ->where('is_active', true)
            ->where('is_plan_eligible', true)
            ->where('rank', '<=', $selected->rank)
            ->orderBy('rank')
            ->get()
            ->map(fn (MediaTier $tier) => [
                'media_tier_id' => $tier->id,
                'photo_maximum' => $photoQuota,
                'video_maximum' => $videoQuota,
            ])->all();
    }

    public function limitsForPlan(LicenceType $plan): array
    {
        $plan->loadMissing('tierLimits');
        if ($plan->tierLimits->isNotEmpty()) {
            return $plan->tierLimits->map(fn ($limit) => [
                'media_tier_id' => (int) $limit->media_tier_id,
                'photo_maximum' => (int) $limit->photo_maximum,
                'video_maximum' => (int) $limit->video_maximum,
            ])->all();
        }

        return $this->legacyTierLimits(
            $plan->media_tier_id ? (int) $plan->media_tier_id : null,
            (int) $plan->photo_quota,
            (int) $plan->video_quota,
        );
    }

    public function analyzePlan(LicenceType $plan): array
    {
        $targetBps = (int) data_get($plan->financial_analysis, 'customer_value_target_bps', 0);
        $minimumCustomerValue = $targetBps > 0
            ? $this->percentageCost($this->money->fromDecimal((string) $plan->price), $targetBps)
            : null;

        return $this->analyze(
            $this->money->fromDecimal((string) $plan->price),
            (int) $plan->photo_quota,
            (int) $plan->video_quota,
            $this->limitsForPlan($plan),
            minimumCustomerValueMinor: $minimumCustomerValue,
            customerValueTargetBps: $targetBps ?: null,
        );
    }

    public function snapshotForPlan(LicenceType $plan): array
    {
        $analysis = $this->analyzePlan($plan);
        $settings = $this->activeSettings();

        return [
            'settings_version' => $analysis['settings_version'],
            'currency' => $analysis['currency'],
            'financial_safety_status' => $analysis['financial_safety_status'],
            'tier_limits' => $analysis['tier_limits'],
            'max_creator_liability_minor' => $analysis['max_creator_liability_minor'],
            'total_non_payout_cost_minor' => $analysis['total_non_payout_cost_minor'],
            'required_profit_minor' => $analysis['required_profit_minor'],
            'worst_case_profit_minor' => $analysis['worst_case_profit_minor'],
            'worst_case_margin_bps' => $analysis['worst_case_margin_bps'],
            'assumptions' => $settings ? [
                'minimum_profit_minor' => $settings->minimum_profit_minor,
                'minimum_margin_bps' => $settings->minimum_margin_bps,
                'operating_cost_bps' => $settings->operating_cost_bps,
                'fixed_operating_cost_minor' => $settings->fixed_operating_cost_minor,
                'risk_reserve_bps' => $settings->risk_reserve_bps,
                'tax_bps' => $settings->tax_bps,
                'photo_allocation_bps' => $settings->photo_allocation_bps,
                'tier_distribution' => $settings->tier_distribution,
                'payment_fees' => $settings->paymentFees->map(fn ($fee) => [
                    'payment_method' => $fee->payment_method,
                    'percentage_bps' => $fee->percentage_bps,
                    'fixed_fee_minor' => $fee->fixed_fee_minor,
                    'is_enabled' => $fee->is_enabled,
                ])->values()->all(),
            ] : null,
        ];
    }

    private function hydrateTierLimits(array $tierLimits): Collection
    {
        $tiers = MediaTier::query()
            ->whereIn('id', collect($tierLimits)->pluck('media_tier_id')->filter()->unique())
            ->where('is_active', true)
            ->where('is_plan_eligible', true)
            ->get()
            ->keyBy('id');

        return collect($tierLimits)->map(function (array $limit) use ($tiers) {
            $tier = $tiers->get((int) ($limit['media_tier_id'] ?? 0));
            if (! $tier) {
                return null;
            }

            return [
                'media_tier_id' => $tier->id,
                'tier_name' => $tier->name,
                'tier_rank' => (int) $tier->rank,
                'photo_maximum' => max(0, (int) ($limit['photo_maximum'] ?? $limit['photo_max'] ?? 0)),
                'video_maximum' => max(0, (int) ($limit['video_maximum'] ?? $limit['video_max'] ?? 0)),
                'payout_cap_photo_minor' => $this->money->fromDecimal((string) $tier->payout_cap_photo),
                'payout_cap_video_minor' => $this->money->fromDecimal((string) $tier->payout_cap_video),
                'max_price_photo_minor' => $this->money->fromDecimal((string) $tier->max_price_photo),
                'max_price_video_minor' => $this->money->fromDecimal((string) $tier->max_price_video),
            ];
        })->filter()->sortBy('tier_rank')->values();
    }

    private function maximumExposure(int $total, Collection $limits, string $maximumKey, string $valueKey): int
    {
        $remaining = $total;
        $exposure = 0;
        foreach ($limits->sortByDesc($valueKey) as $limit) {
            $quantity = min($remaining, (int) $limit[$maximumKey]);
            $exposure += $quantity * (int) $limit[$valueKey];
            $remaining -= $quantity;
            if ($remaining === 0) {
                break;
            }
        }

        return $exposure;
    }

    private function costsAtPrice(int $priceMinor, PlanFinancialSetting $settings): array
    {
        $feeCandidates = $settings->paymentFees
            ->where('is_enabled', true)
            ->map(fn ($fee) => [
                'method' => $fee->payment_method,
                'minor' => $this->percentageCost($priceMinor, (int) $fee->percentage_bps)
                    + (int) $fee->fixed_fee_minor,
            ])
            ->sortByDesc('minor')
            ->values();
        $worstFee = $feeCandidates->first();
        $tax = $this->percentageCost($priceMinor, (int) $settings->tax_bps);
        $operating = $this->percentageCost($priceMinor, (int) $settings->operating_cost_bps)
            + (int) $settings->fixed_operating_cost_minor;
        $risk = $this->percentageCost($priceMinor, (int) $settings->risk_reserve_bps);
        $payment = (int) ($worstFee['minor'] ?? 0);

        return [
            'payment_processing_minor' => $payment,
            'payment_method' => $worstFee['method'] ?? null,
            'tax_minor' => $tax,
            'operating_minor' => $operating,
            'risk_minor' => $risk,
            'total_minor' => $payment + $tax + $operating + $risk,
        ];
    }

    private function unknownCosts(): array
    {
        return [
            'payment_processing_minor' => null,
            'payment_method' => null,
            'tax_minor' => null,
            'operating_minor' => null,
            'risk_minor' => null,
            'total_minor' => null,
        ];
    }

    private function minimumSustainablePrice(
        int $creatorLiability,
        int $catalogValue,
        PlanFinancialSetting $settings,
    ): ?int {
        $isSafe = function (int $price) use ($creatorLiability, $catalogValue, $settings): bool {
            $costs = $this->costsAtPrice($price, $settings);
            $profit = $price - (int) $costs['total_minor'] - $creatorLiability;
            $required = max(
                (int) $settings->minimum_profit_minor,
                $this->percentageCost($price, (int) $settings->minimum_margin_bps),
            );

            return $price >= $catalogValue && $profit >= $required;
        };

        $low = 0;
        $high = max(100, $catalogValue, $creatorLiability + (int) $settings->fixed_operating_cost_minor + (int) $settings->minimum_profit_minor);
        while (! $isSafe($high) && $high < 1_000_000_000_000) {
            $high *= 2;
        }
        if (! $isSafe($high)) {
            return null;
        }

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($isSafe($mid)) {
                $high = $mid;
            } else {
                $low = $mid + 1;
            }
        }

        return $low;
    }

    private function percentageCost(int $amountMinor, int $basisPoints): int
    {
        return intdiv(($amountMinor * $basisPoints) + 9999, 10000);
    }

    private function signedRatioBps(int $numerator, int $denominator): int
    {
        if ($numerator >= 0) {
            return intdiv($numerator * 10000, $denominator);
        }

        return -intdiv((abs($numerator) * 10000) + $denominator - 1, $denominator);
    }
}
