<?php

namespace App\Services;

use App\Models\MediaTier;
use App\Models\PlanFinancialSetting;
use Illuminate\Support\Collection;
use LengthException;
use LogicException;

/**
 * Recommends plan quotas with an exact optimizer.
 *
 * With every Pro/Premium ceiling set to the exact number of credits it may take
 * and the Basic ceiling equal to the shared total, the engine's worst-case
 * liability and potential Cart value become linear in the credit counts. Every
 * other ceiling choice is dominated by such a configuration, so searching all
 * count vectors with PlanQuotaOptimizer covers every valid combination.
 */
class PlanRecommendationService
{
    public const CUSTOMER_VALUE_TARGET_BPS = 11000;

    /** Highest selling price the admin plan form accepts ($999,999.99). */
    public const MAX_PLAN_PRICE_MINOR = 99_999_999;

    /** Whole-dollar prices examined exactly before falling back to the proven sufficient price. */
    private const MAX_PRICE_SCAN_STEPS = 20_000;

    private const MAX_ALTERNATIVES = 3;

    public function __construct(
        private readonly PlanFinancialEngine $engine,
        private readonly MinorMoney $money,
    ) {}

    public function recommend(string|float|int $price): array
    {
        $priceMinor = $this->money->fromDecimal((string) $price);
        $configuration = $this->engine->configurationStatus();
        if (! $configuration['is_complete']) {
            return $this->unavailable(
                'unconfigured',
                'financial_assumptions_missing',
                'Financial assumptions must be configured before quotas can be recommended.',
                missing: $configuration['missing'],
            );
        }

        if ($priceMinor <= 0) {
            return $this->unavailable('invalid_price', 'invalid_price', 'Enter a positive plan price.');
        }

        $settings = $configuration['settings'];
        $tiers = MediaTier::query()
            ->where('is_active', true)
            ->where('is_plan_eligible', true)
            ->orderBy('rank')
            ->get();
        $credits = $this->creditTypes($tiers, $settings);
        if ($credits === []) {
            return $this->unavailable(
                'no_safe_quota',
                'no_valued_credit_types',
                'No eligible media tier has a positive maximum price, so no credit adds potential Cart value.',
            );
        }

        try {
            $search = $this->searchAtPrice($priceMinor, $settings, $credits);
        } catch (LengthException) {
            return $this->unavailable(
                'search_limit',
                'search_space_too_large',
                'This price and tier configuration exceed the exact optimizer memory guard. No unverified recommendation is returned.',
            );
        }

        $proof = $this->proof($search, $settings, $credits);
        $target = $search['target_minor'];
        $common = [
            'price' => $this->money->toDecimal($priceMinor),
            'price_minor' => $priceMinor,
            'currency' => $settings->currency,
            'settings_version' => $settings->version,
            'minimum_customer_value_minor' => $target,
            'customer_value_target_bps' => self::CUSTOMER_VALUE_TARGET_BPS,
            'potential_value_is_not_guaranteed_savings' => true,
            'proof' => $proof,
            'missing_assumptions' => [],
        ];

        if ($search['max_value_minor'] === 0) {
            $minimumPrice = $this->minimumPriceForCredit($credits, $tiers, $settings);
            $common['proof']['minimum_price_for_one_credit_minor'] = $minimumPrice;

            return $common + $this->unavailable(
                'no_safe_quota',
                'no_profit_safe_quota',
                sprintf(
                    'The profit-safe creator liability budget at this price is %s, which cannot cover the worst-case payout of even one credit (%s). The minimum sustainable price for one credit is %s.',
                    $this->moneyText($search['liability_budget_minor'], $settings->currency),
                    $this->moneyText(min(array_column($credits, 'cost')), $settings->currency),
                    $minimumPrice === null ? 'unavailable' : $this->moneyText($minimumPrice, $settings->currency),
                ),
            ) + ['alternatives' => [], 'recommendation' => null, 'closest_safe_configuration' => null];
        }

        if ($proof['target_feasible_at_price']) {
            [$recommended, $alternatives] = $this->targetOptions($search, $settings, $tiers, $credits, $priceMinor);
            if ($recommended === null) {
                throw new LogicException('A feasible target configuration failed engine validation.');
            }

            return $common + [
                'is_available' => true,
                'status' => 'target_met',
                'reason' => sprintf(
                    'Recommended quotas keep worst-case profit at or above the configured requirement and give a potential individual Cart value of %s (%s%% of the plan price).',
                    $this->moneyText($recommended['potential_cart_value_minor'], $settings->currency),
                    $this->percent($recommended['potential_cart_value_ratio_bps']),
                ),
                'photo_quota' => $recommended['photo_quota'],
                'video_quota' => $recommended['video_quota'],
                'media_tier_id' => $recommended['media_tier_id'],
                'tier_limits' => $recommended['tier_limits'],
                'analysis' => $recommended['analysis'],
                'potential_cart_value_minor' => $recommended['potential_cart_value_minor'],
                'potential_cart_value_ratio_bps' => $recommended['potential_cart_value_ratio_bps'],
                'recommendation' => $recommended,
                'closest_safe_configuration' => null,
                'alternatives' => $alternatives,
            ];
        }

        $closest = $this->closestSafeOption($search, $settings, $tiers, $credits);
        $alternatives = [];
        try {
            $alternatives = $this->priceAlternatives($search, $settings, $tiers, $credits, $proof);
        } catch (LengthException) {
            // A price alternative that cannot be verified exactly is simply not offered.
        }
        $balanced = $this->balancedOption($search, $settings, $tiers, $credits, $priceMinor);
        if ($balanced !== null && ! $this->sameConfiguration($balanced, $closest)) {
            $alternatives[] = $balanced;
        }

        return $common + [
            'is_available' => false,
            'status' => 'target_not_met',
            'failure_code' => 'profit_and_customer_value_incompatible',
            'reason' => sprintf(
                'No recommendation can satisfy both the configured platform profit requirement and the 10%% customer-value target. The highest profit-safe potential individual Cart value is %s; the required target is %s. %s',
                $this->moneyText($search['max_value_minor'], $settings->currency),
                $this->moneyText($target, $settings->currency),
                $this->bottleneckSentence($proof, $settings->currency),
            ),
            'analysis' => $closest['analysis'] ?? null,
            'recommendation' => null,
            'closest_safe_configuration' => $closest,
            'alternatives' => array_slice($alternatives, 0, self::MAX_ALTERNATIVES),
        ];
    }

    /**
     * One credit type per (tier, media). With tight ceilings, a customer who
     * meets a non-Basic ceiling below the Basic cap can still redeem Basic media
     * instead, so its worst-case payout and value are max(tier, Basic).
     *
     * @return array<int, array<string, mixed>>
     */
    private function creditTypes(Collection $tiers, PlanFinancialSetting $settings): array
    {
        $basic = $tiers->first();
        if (! $basic) {
            return [];
        }
        $distribution = collect($settings->tier_distribution ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (int) $value]);
        $photoShare = (int) $settings->photo_allocation_bps;
        $credits = [];

        foreach (['photo', 'video'] as $media) {
            $share = $media === 'photo' ? $photoShare : 10000 - $photoShare;
            $basicCap = $this->money->fromDecimal((string) $basic->{"payout_cap_{$media}"});
            $basicValue = $this->money->fromDecimal((string) $basic->{"max_price_{$media}"});
            $basicKey = null;

            foreach ($tiers as $tier) {
                $isBasic = (int) $tier->id === (int) $basic->id;
                $cap = $this->money->fromDecimal((string) $tier->{"payout_cap_{$media}"});
                $value = $this->money->fromDecimal((string) $tier->{"max_price_{$media}"});
                $cost = $isBasic ? $cap : max($cap, $basicCap);
                $potential = $isBasic ? $value : max($value, $basicValue);
                $weight = (int) ($distribution[(string) $tier->id] ?? 0) * $share;

                if (! $isBasic && $cost === $basicCap && $potential === $basicValue) {
                    if ($basicKey !== null) {
                        $credits[$basicKey]['weight'] += $weight;
                    }

                    continue;
                }
                if ($potential <= 0) {
                    continue;
                }

                $credits[] = [
                    'tier_id' => (int) $tier->id,
                    'tier_name' => $tier->name,
                    'tier_rank' => (int) $tier->rank,
                    'is_basic' => $isBasic,
                    'media_type' => $media,
                    'cost' => $cost,
                    'value' => $potential,
                    'payout_cap_minor' => $cap,
                    'max_price_minor' => $value,
                    'weight' => $weight,
                ];
                if ($isBasic) {
                    $basicKey = array_key_last($credits);
                }
            }
        }

        // Residual credits are reconstructed in this order: the admin's
        // distribution first, then lower tiers, then photos.
        usort($credits, fn ($a, $b) => [$b['weight'], $a['tier_rank'], $a['media_type']]
            <=> [$a['weight'], $b['tier_rank'], $b['media_type']]);

        return $credits;
    }

    private function searchAtPrice(int $priceMinor, PlanFinancialSetting $settings, array $credits): array
    {
        $budget = $this->engine->profitBudget($priceMinor, $settings);
        $liabilityBudget = $budget['liability_budget_minor'];
        $target = $this->targetFor($priceMinor);
        $optimizer = new PlanQuotaOptimizer(
            array_column($credits, 'cost'),
            array_column($credits, 'value'),
            $this->valueHorizon($credits, max(0, $liabilityBudget), $target),
        );

        return [
            'price_minor' => $priceMinor,
            'budget' => $budget,
            'liability_budget_minor' => $liabilityBudget,
            'target_minor' => $target,
            'optimizer' => $optimizer,
            'max_value_minor' => $optimizer->maxValueWithin($liabilityBudget) ?? 0,
            'value_is_unbounded' => in_array(0, array_column($credits, 'cost'), true),
            'max_scale' => $this->maxScale($credits, $liabilityBudget, $optimizer->horizonMinor()),
        ];
    }

    /**
     * Upper bound on any potential value the searches at this budget can need:
     * the target plus one credit, or budget x best value-per-payout ratio.
     */
    private function valueHorizon(array $credits, int $liabilityBudget, int $target): int
    {
        $horizon = $target + max(array_column($credits, 'value'));
        foreach ($credits as $credit) {
            // A zero payout cap makes extra value free; nothing beyond the target is needed.
            if ($credit['cost'] === 0) {
                return $horizon;
            }
        }
        foreach ($credits as $credit) {
            $horizon = max($horizon, intdiv($liabilityBudget * $credit['value'], $credit['cost']));
        }

        return $horizon;
    }

    /**
     * Option that meets the target while following the configured distribution as
     * far as possible, plus alternatives that trade that balance for value or profit.
     *
     * @return array{0: ?array, 1: array<int, array>}
     */
    private function targetOptions(array $search, PlanFinancialSetting $settings, Collection $tiers, array $credits, int $requestedPriceMinor): array
    {
        $target = $search['target_minor'];
        $optimizer = $search['optimizer'];
        $budget = $search['liability_budget_minor'];
        $priceMinor = $search['price_minor'];

        $scale = $this->largestScale($search['max_scale'], fn (int $k) => $this->valueAtScale($search, $credits, $k) >= $target);
        $recommended = $this->scaledOption('recommended', $search, $settings, $tiers, $credits, $scale, true, $requestedPriceMinor);
        if ($recommended === null || $priceMinor !== $requestedPriceMinor) {
            return [$recommended, []];
        }

        $alternatives = [];
        if (! $search['value_is_unbounded']) {
            $maxValueScale = $this->largestScale(
                $search['max_scale'],
                fn (int $k) => $this->valueAtScale($search, $credits, $k) >= $search['max_value_minor'],
            );
            $alternatives[] = $this->scaledOption('max_value', $search, $settings, $tiers, $credits, $maxValueScale, true, $requestedPriceMinor);
        }

        // Highest worst-case profit that still meets the target: minimum liability
        // over every scale, preferring the larger (more balanced) scale on ties.
        $cheapest = null;
        for ($k = 0; $k <= $search['max_scale']; $k++) {
            $floors = $this->floorCounts($credits, $k);
            $floorLiability = $this->total($credits, $floors, 'cost');
            $residual = $optimizer->minLiabilityForValueAtLeast($target - $this->total($credits, $floors, 'value'), false);
            if ($residual === null || $floorLiability + $residual['liability_minor'] > $budget) {
                continue;
            }
            $liability = $floorLiability + $residual['liability_minor'];
            if ($cheapest === null || $liability <= $cheapest['liability']) {
                $cheapest = ['liability' => $liability, 'scale' => $k];
            }
        }
        if ($cheapest !== null) {
            $floors = $this->floorCounts($credits, $cheapest['scale']);
            $residual = $optimizer->minLiabilityForValueAtLeast($target - $this->total($credits, $floors, 'value'));
            $alternatives[] = $this->option(
                'max_profit',
                $priceMinor,
                $this->withResidual($optimizer, $credits, $floors, $residual['value_minor']),
                $settings,
                $tiers,
                $credits,
                true,
                $cheapest['scale'],
                $requestedPriceMinor,
            );
        }
        $alternatives[] = $this->balancedOption($search, $settings, $tiers, $credits, $requestedPriceMinor, false);

        return [$recommended, $this->distinctOptions($alternatives, [$recommended])];
    }

    /**
     * The profit-safe configuration with the highest potential value that can be
     * saved as a standard plan (potential value <= price), balanced on ties.
     */
    private function closestSafeOption(array $search, PlanFinancialSetting $settings, Collection $tiers, array $credits): ?array
    {
        $priceMinor = $search['price_minor'];
        $cap = $search['max_value_minor'] > $priceMinor ? $priceMinor : null;
        $best = $search['optimizer']->maxValueWithin($search['liability_budget_minor'], $cap) ?? 0;
        if ($best === 0) {
            return null;
        }
        $scale = $this->largestScale(
            $search['max_scale'],
            fn (int $k) => ($this->valueAtScale($search, $credits, $k, $cap) ?? -1) >= $best,
        );

        return $this->scaledOption('closest_safe', $search, $settings, $tiers, $credits, $scale, false, $priceMinor, $cap);
    }

    /**
     * Follows the configured distribution as far as the budget allows. Below the
     * target it is offered only as a standard plan (potential value <= price).
     */
    private function balancedOption(
        array $search,
        PlanFinancialSetting $settings,
        Collection $tiers,
        array $credits,
        int $requestedPriceMinor,
        bool $allowStandard = true,
    ): ?array {
        $scale = $search['max_scale'];
        $value = $this->valueAtScale($search, $credits, $scale);
        if ($value >= $search['target_minor']) {
            return $this->scaledOption('balanced', $search, $settings, $tiers, $credits, $scale, true, $requestedPriceMinor);
        }
        if (! $allowStandard) {
            return null;
        }
        $standardValue = $this->valueAtScale($search, $credits, $scale, $search['price_minor']);
        if ($standardValue === null || $standardValue === 0) {
            return null;
        }

        return $this->scaledOption('balanced', $search, $settings, $tiers, $credits, $scale, false, $requestedPriceMinor, $search['price_minor']);
    }

    /**
     * Prices above the entered one at which the target becomes feasible.
     */
    private function priceAlternatives(array $search, PlanFinancialSetting $settings, Collection $tiers, array $credits, array $proof): array
    {
        if (! $proof['target_feasible_at_some_price'] || $proof['guaranteed_target_price_minor'] === null) {
            return [];
        }
        $priceMinor = $search['price_minor'];
        $guaranteed = $this->ceilDollar($proof['guaranteed_target_price_minor']);
        $high = min($guaranteed, self::MAX_PLAN_PRICE_MINOR);
        $start = $this->ceilDollar($priceMinor + 1);
        $prices = [];

        $scanHorizon = $this->valueHorizon($credits, $high, $this->targetFor($high));
        $states = PlanQuotaOptimizer::stateCount(array_column($credits, 'cost'), array_column($credits, 'value'), $scanHorizon);
        if ($start <= $high
            && intdiv($high - $start, 100) < self::MAX_PRICE_SCAN_STEPS
            && $states <= PlanQuotaOptimizer::MAX_TABLE_STATES) {
            $scan = new PlanQuotaOptimizer(array_column($credits, 'cost'), array_column($credits, 'value'), $scanHorizon);
            $feasible = fn (int $price) => ($scan->maxValueWithin($this->engine->profitBudget($price, $settings)['liability_budget_minor']) ?? -1)
                >= $this->targetFor($price);
            for ($candidate = $start; $candidate <= $high; $candidate += 100) {
                if ($feasible($candidate)) {
                    $prices[] = $candidate;
                    break;
                }
            }
            // Offer the next whole-$10 price as well, when it is feasible.
            if ($prices !== []) {
                for ($candidate = intdiv($prices[0], 1000) * 1000 + 1000; $candidate <= $high; $candidate += 1000) {
                    if ($feasible($candidate)) {
                        $prices[] = $candidate;
                        break;
                    }
                }
            }
        } elseif ($guaranteed <= self::MAX_PLAN_PRICE_MINOR) {
            $prices[] = $guaranteed;
        }

        $options = [];
        foreach ($prices as $candidate) {
            $alternative = $this->searchAtPrice($candidate, $settings, $credits);
            [$recommended] = $this->targetOptions($alternative, $settings, $tiers, $credits, $priceMinor);
            if ($recommended !== null) {
                $options[] = ['key' => 'price_alternative'] + $recommended;
            }
        }

        return $options;
    }

    private function proof(array $search, PlanFinancialSetting $settings, array $credits): array
    {
        $priceMinor = $search['price_minor'];
        $budget = $search['budget'];
        $liabilityBudget = $search['liability_budget_minor'];
        $target = $search['target_minor'];
        $maxValue = $search['max_value_minor'];

        // Lowest payout per unit of potential value (value / payout maximal).
        $best = $credits[0];
        foreach ($credits as $credit) {
            if ($credit['value'] * $best['cost'] > $best['value'] * $credit['cost']) {
                $best = $credit;
            }
        }

        $fees = $settings->paymentFees->where('is_enabled', true);
        $feeRateBps = (int) $fees->max('percentage_bps');
        $variableCostBps = $feeRateBps + (int) $settings->tax_bps + (int) $settings->operating_cost_bps + (int) $settings->risk_reserve_bps;
        $marginBps = (int) $settings->minimum_margin_bps;
        $creatorShareBps = 10000 - $variableCostBps - $marginBps;
        $fixedFloor = (int) $fees->where('percentage_bps', $feeRateBps)->max('fixed_fee_minor')
            + (int) $settings->fixed_operating_cost_minor;
        $fixedCeiling = (int) $fees->max('fixed_fee_minor') + (int) $settings->fixed_operating_cost_minor
            + (int) $settings->minimum_profit_minor + 5;

        // Potential value can never exceed (best value / payout) x liability budget,
        // and the budget can never exceed price x creator share - fixed costs.
        // So 110% is impossible at every price when slope <= 0 (strictly, or with fixed costs).
        $slope = $best['value'] * $creatorShareBps - self::CUSTOMER_VALUE_TARGET_BPS * $best['cost'];
        $feasibleSomewhere = $creatorShareBps > 0
            && ($best['cost'] === 0 || $slope > 0 || ($slope === 0 && $fixedFloor === 0));
        $guaranteedPrice = null;
        if ($feasibleSomewhere && $best['cost'] === 0) {
            $guaranteedPrice = intdiv((10000 * $fixedCeiling) + $creatorShareBps - 1, $creatorShareBps);
        } elseif ($feasibleSomewhere && $slope > 0) {
            $numerator = 10000 * (($best['value'] * $fixedCeiling) + ($best['cost'] * $best['value']) + $best['cost']);
            $guaranteedPrice = intdiv($numerator + $slope - 1, $slope);
        }

        return [
            'price_minor' => $priceMinor,
            'target_minor' => $target,
            'target_bps' => self::CUSTOMER_VALUE_TARGET_BPS,
            'payment_processing_cost_minor' => $budget['payment_processing_cost_minor'],
            'payment_fee_method' => $budget['payment_fee_method'],
            'tax_cost_minor' => $budget['tax_cost_minor'],
            'operating_cost_minor' => $budget['operating_cost_minor'],
            'risk_reserve_minor' => $budget['risk_reserve_minor'],
            'total_non_payout_cost_minor' => $budget['total_non_payout_cost_minor'],
            'net_plan_revenue_minor' => $budget['net_plan_revenue_minor'],
            'minimum_profit_minor' => (int) $settings->minimum_profit_minor,
            'minimum_margin_bps' => $marginBps,
            'required_profit_minor' => $budget['required_profit_minor'],
            'liability_budget_minor' => $liabilityBudget,
            'best_value_credit' => [
                'tier_id' => $best['tier_id'],
                'tier_name' => $best['tier_name'],
                'media_type' => $best['media_type'],
                'payout_cap_minor' => $best['cost'],
                'max_price_minor' => $best['value'],
                'payout_to_value_bps' => intdiv(($best['cost'] * 10000) + $best['value'] - 1, $best['value']),
            ],
            'value_upper_bound_minor' => $best['cost'] > 0
                ? intdiv(max(0, $liabilityBudget) * $best['value'], $best['cost'])
                : null,
            'max_profit_safe_value_minor' => $maxValue,
            'max_profit_safe_value_ratio_bps' => intdiv($maxValue * 10000, $priceMinor),
            'value_is_unbounded' => $search['value_is_unbounded'],
            'target_shortfall_minor' => max(0, $target - $maxValue),
            'target_feasible_at_price' => $maxValue >= $target,
            'target_feasible_at_some_price' => $feasibleSomewhere,
            'variable_cost_bps' => $variableCostBps,
            'creator_share_ceiling_bps' => $creatorShareBps,
            'asymptotic_value_ratio_bps' => $best['cost'] > 0 ? intdiv(max(0, $creatorShareBps) * $best['value'], $best['cost']) : null,
            'required_payout_to_value_bps' => intdiv(max(0, $creatorShareBps) * 10000, self::CUSTOMER_VALUE_TARGET_BPS),
            'guaranteed_target_price_minor' => $guaranteedPrice,
            'standard_plan_cap_blocks_value' => $maxValue > $priceMinor && $maxValue < $target,
            'minimum_price_for_one_credit_minor' => null,
            'optimizer' => [
                'method' => 'exact_unbounded_knapsack',
                'credit_types' => count($credits),
                'table_states' => $search['optimizer']->tableStates(),
                'value_unit_minor' => $search['optimizer']->valueUnitMinor(),
            ],
        ];
    }

    private function bottleneckSentence(array $proof, string $currency): string
    {
        $best = $proof['best_value_credit'];
        $sentence = sprintf(
            'Every %s of potential Cart value costs at least %s of worst-case creator payout (best credit: %s %s, payout cap %s for a %s maximum price), so a liability budget of %s supports at most %s of potential value.',
            $this->moneyText(100, $currency),
            $this->moneyText(intdiv($best['payout_to_value_bps'], 100), $currency),
            $best['tier_name'],
            $best['media_type'],
            $this->moneyText($best['payout_cap_minor'], $currency),
            $this->moneyText($best['max_price_minor'], $currency),
            $this->moneyText(max(0, $proof['liability_budget_minor']), $currency),
            $this->moneyText((int) $proof['value_upper_bound_minor'], $currency),
        );
        if (! $proof['target_feasible_at_some_price']) {
            $sentence .= sprintf(
                ' After variable costs (%s%%) and the %s%% minimum margin, at most %s%% of any price can fund creator payouts, which caps potential value at %s%% of the price at every price. Reaching 110%% would require a payout-to-value ratio of at most %s%%.',
                $this->percent($proof['variable_cost_bps']),
                $this->percent($proof['minimum_margin_bps']),
                $this->percent($proof['creator_share_ceiling_bps']),
                $this->percent((int) $proof['asymptotic_value_ratio_bps']),
                $this->percent($proof['required_payout_to_value_bps']),
            );
        } elseif ($proof['guaranteed_target_price_minor'] !== null) {
            $sentence .= sprintf(
                ' The target is mathematically guaranteed from %s.',
                $this->moneyText($proof['guaranteed_target_price_minor'], $currency),
            );
        }

        return $sentence;
    }

    /**
     * Engine-computed minimum sustainable price of the cheapest single credit.
     */
    private function minimumPriceForCredit(array $credits, Collection $tiers, PlanFinancialSetting $settings): ?int
    {
        $cheapest = $credits[0];
        foreach ($credits as $credit) {
            if ([$credit['cost'], $credit['value']] < [$cheapest['cost'], $cheapest['value']]) {
                $cheapest = $credit;
            }
        }
        $isPhoto = $cheapest['media_type'] === 'photo';
        $limits = collect([(int) $tiers->first()->id, $cheapest['tier_id']])->unique()->map(fn ($tierId) => [
            'media_tier_id' => $tierId,
            'photo_maximum' => $isPhoto ? 1 : 0,
            'video_maximum' => $isPhoto ? 0 : 1,
        ])->values()->all();

        return $this->engine->analyze(
            $cheapest['value'],
            $isPhoto ? 1 : 0,
            $isPhoto ? 0 : 1,
            $limits,
            $settings,
        )['minimum_sustainable_price_minor'];
    }

    private function valueAtScale(array $search, array $credits, int $scale, ?int $capMinor = null): ?int
    {
        $floors = $this->floorCounts($credits, $scale);
        $floorValue = $this->total($credits, $floors, 'value');
        if ($capMinor !== null && $floorValue > $capMinor) {
            return null;
        }
        $residual = $search['optimizer']->maxValueWithin(
            $search['liability_budget_minor'] - $this->total($credits, $floors, 'cost'),
            $capMinor === null ? null : $capMinor - $floorValue,
        );

        return $residual === null ? null : $floorValue + $residual;
    }

    private function scaledOption(
        string $key,
        array $search,
        PlanFinancialSetting $settings,
        Collection $tiers,
        array $credits,
        int $scale,
        bool $valueTarget,
        int $requestedPriceMinor,
        ?int $capMinor = null,
    ): ?array {
        $floors = $this->floorCounts($credits, $scale);
        $floorValue = $this->total($credits, $floors, 'value');
        $residual = $search['optimizer']->maxValueWithin(
            $search['liability_budget_minor'] - $this->total($credits, $floors, 'cost'),
            $capMinor === null ? null : $capMinor - $floorValue,
        );
        if ($residual === null) {
            return null;
        }

        return $this->option(
            $key,
            $search['price_minor'],
            $this->withResidual($search['optimizer'], $credits, $floors, $residual),
            $settings,
            $tiers,
            $credits,
            $valueTarget,
            $scale,
            $requestedPriceMinor,
        );
    }

    /**
     * Turns credit counts into quotas and tight ceilings, then lets the engine
     * revalidate them. Anything the engine does not confirm is discarded.
     */
    private function option(
        string $key,
        int $priceMinor,
        array $counts,
        PlanFinancialSetting $settings,
        Collection $tiers,
        array $credits,
        bool $valueTarget,
        int $scale,
        int $requestedPriceMinor,
    ): ?array {
        $quota = ['photo' => 0, 'video' => 0];
        $byTier = [];
        foreach ($credits as $index => $credit) {
            $quota[$credit['media_type']] += $counts[$index];
            $byTier[$credit['tier_id']][$credit['media_type']] = $counts[$index];
        }
        if ($quota['photo'] + $quota['video'] === 0) {
            return null;
        }

        $basicId = (int) $tiers->first()->id;
        $limits = $tiers->map(fn ($tier) => [
            'media_tier_id' => (int) $tier->id,
            'photo_maximum' => (int) $tier->id === $basicId ? $quota['photo'] : (int) ($byTier[$tier->id]['photo'] ?? 0),
            'video_maximum' => (int) $tier->id === $basicId ? $quota['video'] : (int) ($byTier[$tier->id]['video'] ?? 0),
        ])->filter(fn ($limit) => $limit['photo_maximum'] > 0 || $limit['video_maximum'] > 0)->values()->all();

        $target = $this->targetFor($priceMinor);
        $analysis = $this->engine->analyze(
            $priceMinor,
            $quota['photo'],
            $quota['video'],
            $limits,
            $settings,
            $valueTarget ? $target : null,
            $valueTarget ? self::CUSTOMER_VALUE_TARGET_BPS : null,
        );
        $liability = $this->total($credits, $counts, 'cost');
        $value = $this->total($credits, $counts, 'value');
        if (! $analysis['is_valid']
            || $analysis['max_creator_liability_minor'] !== $liability
            || $analysis['max_catalog_value_minor'] !== $value) {
            report(new LogicException("Plan recommendation option '{$key}' was rejected by engine revalidation."));

            return null;
        }

        $recommendedIds = collect($limits)->pluck('media_tier_id')->all();

        return [
            'key' => $key,
            'price_minor' => $priceMinor,
            'price' => $this->money->toDecimal($priceMinor),
            'requires_price_change' => $priceMinor !== $requestedPriceMinor,
            'validation_mode' => $valueTarget ? 'value_target' : 'standard',
            'recommendation_value_target_bps' => $valueTarget ? self::CUSTOMER_VALUE_TARGET_BPS : null,
            'meets_value_target' => $value >= $target,
            'photo_quota' => $quota['photo'],
            'video_quota' => $quota['video'],
            'media_tier_id' => $tiers->whereIn('id', $recommendedIds)->sortByDesc('rank')->first()?->id,
            'tier_limits' => $limits,
            'max_creator_liability_minor' => $liability,
            'worst_case_profit_minor' => $analysis['worst_case_profit_minor'],
            'worst_case_margin_bps' => $analysis['worst_case_margin_bps'],
            'required_profit_minor' => $analysis['required_profit_minor'],
            'potential_cart_value_minor' => $value,
            'potential_cart_value_ratio_bps' => $analysis['potential_cart_value_ratio_bps'],
            'minimum_customer_value_minor' => $target,
            'distribution_scale' => $scale,
            'distribution_fit_bps' => $this->distributionFit($credits, $counts),
            'analysis' => $analysis,
        ];
    }

    /**
     * Credits that follow the configured distribution at scale k. Every count is
     * non-decreasing in k, so feasible sets shrink and values never rise with k.
     */
    private function floorCounts(array $credits, int $scale): array
    {
        $totalWeight = array_sum(array_column($credits, 'weight'));

        return array_map(
            fn ($credit) => $totalWeight > 0 ? intdiv($scale * $credit['weight'], $totalWeight) : 0,
            $credits,
        );
    }

    private function maxScale(array $credits, int $liabilityBudget, int $valueHorizon): int
    {
        if ($liabilityBudget < 0 || array_sum(array_column($credits, 'weight')) === 0) {
            return 0;
        }
        $fits = function (int $scale) use ($credits, $liabilityBudget, $valueHorizon) {
            $floors = $this->floorCounts($credits, $scale);

            return $this->total($credits, $floors, 'cost') <= $liabilityBudget
                && $this->total($credits, $floors, 'value') <= $valueHorizon;
        };
        $low = 0;
        $high = 1;
        while ($fits($high)) {
            $low = $high;
            $high *= 2;
        }
        while ($high - $low > 1) {
            $mid = intdiv($low + $high, 2);
            if ($fits($mid)) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return $low;
    }

    /**
     * Largest k in [0, max] for a predicate that holds at 0 and is monotone (true, then false).
     */
    private function largestScale(int $max, callable $holds): int
    {
        $low = 0;
        $high = $max;
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            if ($holds($mid)) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return $low;
    }

    private function distributionFit(array $credits, array $counts): ?int
    {
        $totalWeight = array_sum(array_column($credits, 'weight'));
        $totalCount = array_sum($counts);
        if ($totalWeight === 0 || $totalCount === 0) {
            return null;
        }
        $distance = 0.0;
        foreach ($credits as $index => $credit) {
            $distance += abs(($counts[$index] / $totalCount) - ($credit['weight'] / $totalWeight));
        }

        return (int) round((1 - ($distance / 2)) * 10000);
    }

    private function total(array $credits, array $counts, string $field): int
    {
        $total = 0;
        foreach ($credits as $index => $credit) {
            $total += $counts[$index] * $credit[$field];
        }

        return $total;
    }

    /**
     * Adds an optimal residual to the distribution floors. Among the credits that
     * keep the residual optimal, the most under-represented one is taken next.
     */
    private function withResidual(PlanQuotaOptimizer $optimizer, array $credits, array $floors, int $residualMinor): array
    {
        $weights = array_column($credits, 'weight');
        $totalWeight = array_sum($weights);
        $residual = $optimizer->counts($residualMinor, function (array $optimal, array $sofar) use ($floors, $weights, $totalWeight) {
            if ($totalWeight === 0) {
                return $optimal[0];
            }
            $next = array_sum($floors) + array_sum($sofar) + 1;
            $choice = $optimal[0];
            $largestDeficit = null;
            foreach ($optimal as $index) {
                $deficit = ($weights[$index] * $next) - (($floors[$index] + $sofar[$index]) * $totalWeight);
                if ($largestDeficit === null || $deficit > $largestDeficit) {
                    $largestDeficit = $deficit;
                    $choice = $index;
                }
            }

            return $choice;
        });

        return array_map(fn ($a, $b) => $a + $b, $floors, $residual);
    }

    private function distinctOptions(array $options, array $taken): array
    {
        $distinct = [];
        foreach (array_filter($options) as $option) {
            foreach (array_merge($taken, $distinct) as $existing) {
                if ($this->sameConfiguration($option, $existing)) {
                    continue 2;
                }
            }
            $distinct[] = $option;
        }

        return array_slice($distinct, 0, self::MAX_ALTERNATIVES);
    }

    private function sameConfiguration(?array $a, ?array $b): bool
    {
        return $a !== null && $b !== null
            && $a['price_minor'] === $b['price_minor']
            && $a['photo_quota'] === $b['photo_quota']
            && $a['video_quota'] === $b['video_quota']
            && $a['tier_limits'] === $b['tier_limits'];
    }

    private function targetFor(int $priceMinor): int
    {
        return intdiv(($priceMinor * self::CUSTOMER_VALUE_TARGET_BPS) + 9999, 10000);
    }

    private function ceilDollar(int $minor): int
    {
        return intdiv($minor + 99, 100) * 100;
    }

    private function moneyText(int $minor, ?string $currency): string
    {
        if ($minor < 0) {
            return '-'.$this->moneyText(-$minor, $currency);
        }
        $amount = $this->money->toDecimal($minor);

        return strtoupper((string) $currency) === 'USD' ? '$'.$amount : trim($amount.' '.$currency);
    }

    private function percent(int $bps): string
    {
        return rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.');
    }

    private function unavailable(string $status, string $failureCode, string $reason, array $missing = []): array
    {
        return [
            'is_available' => false,
            'status' => $status,
            'failure_code' => $failureCode,
            'reason' => $reason,
            'missing_assumptions' => $missing,
        ];
    }
}
