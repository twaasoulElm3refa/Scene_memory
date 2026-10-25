<?php

namespace Tests\Feature\Api\Plans;

use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\PlanFinancialSetting;
use App\Models\User;
use App\Services\PlanFinancialEngine;
use App\Services\PlanRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlanRecommendationOptimizerTest extends TestCase
{
    use RefreshDatabase;

    private const OPTION_FIELDS = [
        'key', 'price_minor', 'requires_price_change', 'validation_mode', 'recommendation_value_target_bps',
        'meets_value_target', 'photo_quota', 'video_quota', 'media_tier_id', 'tier_limits',
        'max_creator_liability_minor', 'worst_case_profit_minor', 'worst_case_margin_bps',
        'required_profit_minor', 'potential_cart_value_minor', 'potential_cart_value_ratio_bps',
        'minimum_customer_value_minor',
    ];

    public function test_seeded_tiers_cannot_reach_110_percent_at_200_or_any_other_price(): void
    {
        // Seeded tiers pay creators up to 90% of each tier's maximum price.
        // Even with zero fees, tax, operating cost and risk reserve, the 12% margin
        // leaves 88% of the price for payouts: 88% / 90% = 97.8% < 110%.
        $this->configurePlanFinancialSettings(['minimum_profit_minor' => 1, 'minimum_margin_bps' => 1200]);

        $result = $this->recommend('200.00');

        $this->assertFalse($result['is_available']);
        $this->assertSame('target_not_met', $result['status']);
        $this->assertSame('profit_and_customer_value_incompatible', $result['failure_code']);
        $proof = $result['proof'];
        $this->assertSame(17600, $proof['liability_budget_minor']);
        $this->assertSame(9000, $proof['best_value_credit']['payout_to_value_bps']);
        $this->assertSame(19555, $proof['value_upper_bound_minor']);
        $this->assertSame(19500, $proof['max_profit_safe_value_minor']);
        $this->assertSame(22000, $proof['target_minor']);
        $this->assertSame(2500, $proof['target_shortfall_minor']);
        $this->assertFalse($proof['target_feasible_at_some_price']);
        $this->assertSame(8800, $proof['creator_share_ceiling_bps']);
        $this->assertSame(9777, $proof['asymptotic_value_ratio_bps']);
        $this->assertSame(8000, $proof['required_payout_to_value_bps']);
        $this->assertNull($proof['guaranteed_target_price_minor']);
        $this->assertStringContainsString('10% customer-value target', $result['reason']);
        $this->assertStringContainsString('97.77% of the price at every price', $result['reason']);

        $closest = $result['closest_safe_configuration'];
        $this->assertSame('standard', $closest['validation_mode']);
        $this->assertNull($closest['recommendation_value_target_bps']);
        $this->assertSame(19500, $closest['potential_cart_value_minor']);
        $this->assertGreaterThanOrEqual(2400, $closest['worst_case_profit_minor']);
        $this->assertGreaterThanOrEqual(1200, $closest['worst_case_margin_bps']);
        $this->assertTrue($closest['analysis']['is_valid']);
        foreach ($result['alternatives'] as $alternative) {
            $this->assertFalse($alternative['meets_value_target']);
            $this->assertNotSame('price_alternative', $alternative['key']);
        }

        foreach (['50.00', '1000.00', '25000.00', '999999.99'] as $price) {
            $other = $this->recommend($price);
            $this->assertSame('target_not_met', $other['status'], $price);
            $this->assertLessThan($other['proof']['target_minor'], $other['proof']['max_profit_safe_value_minor'], $price);
            $this->assertLessThan(11000, $other['proof']['max_profit_safe_value_ratio_bps'], $price);
        }
    }

    public function test_200_budget_includes_payment_fees_tax_operating_cost_and_risk_reserve(): void
    {
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 500,
            'minimum_margin_bps' => 1200,
            'operating_cost_bps' => 200,
            'fixed_operating_cost_minor' => 50,
            'risk_reserve_bps' => 100,
            'tax_bps' => 500,
        ])->paymentFees()->where('payment_method', 'paypal')->update(['percentage_bps' => 300, 'fixed_fee_minor' => 30]);

        $result = $this->recommend('200.00');
        $proof = $result['proof'];

        $this->assertSame(630, $proof['payment_processing_cost_minor']);
        $this->assertSame('paypal', $proof['payment_fee_method']);
        $this->assertSame(1000, $proof['tax_cost_minor']);
        $this->assertSame(450, $proof['operating_cost_minor']);
        $this->assertSame(200, $proof['risk_reserve_minor']);
        $this->assertSame(2280, $proof['total_non_payout_cost_minor']);
        $this->assertSame(2400, $proof['required_profit_minor']);
        $this->assertSame(15320, $proof['liability_budget_minor']);
        $this->assertSame(17000, $proof['max_profit_safe_value_minor']);
        $this->assertSame(1100, $proof['variable_cost_bps']);
        $this->assertSame(7000, $proof['required_payout_to_value_bps']);

        $closest = $result['closest_safe_configuration'];
        $this->assertSame(2280, $closest['analysis']['total_non_payout_cost_minor']);
        $this->assertSame(20000 - 2280 - $closest['max_creator_liability_minor'], $closest['worst_case_profit_minor']);
        $this->assertGreaterThanOrEqual(2400, $closest['worst_case_profit_minor']);
    }

    public function test_worst_payment_method_fee_sets_the_budget(): void
    {
        $settings = $this->configurePlanFinancialSettings(['minimum_profit_minor' => 1, 'minimum_margin_bps' => 1200]);
        $settings->paymentFees()->where('payment_method', 'paypal')->update(['percentage_bps' => 349, 'fixed_fee_minor' => 49]);
        $settings->paymentFees()->where('payment_method', 'wallet')->update(['percentage_bps' => 500, 'fixed_fee_minor' => 0]);

        $proof = $this->recommend('200.00')['proof'];

        $this->assertSame('wallet', $proof['payment_fee_method']);
        $this->assertSame(1000, $proof['payment_processing_cost_minor']);
        $this->assertSame(20000 - 1000 - 2400, $proof['liability_budget_minor']);
    }

    public function test_exact_optimizer_meets_target_where_the_weighted_distribution_cannot(): void
    {
        $this->customTiers(
            ['photo' => [200, 200], 'video' => [500, 500]],
            ['photo' => [2000, 1000], 'video' => [5000, 2500]],
        );
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 1,
            'minimum_margin_bps' => 1200,
            'tier_distribution' => ['1' => 9500, '2' => 500],
        ]);

        $result = $this->recommend('200.00');

        $this->assertTrue($result['is_available']);
        $this->assertSame('target_met', $result['status']);
        $this->assertSame(35000, $result['proof']['max_profit_safe_value_minor']);
        $recommended = $result['recommendation'];
        $this->assertTrue($recommended['meets_value_target']);
        $this->assertSame('value_target', $recommended['validation_mode']);
        $this->assertGreaterThanOrEqual(22000, $recommended['potential_cart_value_minor']);
        $this->assertGreaterThanOrEqual(2400, $recommended['worst_case_profit_minor']);
        $this->assertGreaterThanOrEqual(1200, $recommended['worst_case_margin_bps']);
        $this->assertSame($recommended['photo_quota'], $result['photo_quota']);
        $this->assertSame($recommended['tier_limits'], $result['tier_limits']);

        // Following the 95/5 distribution alone stays below the target, so the
        // optimizer had to move beyond the weights to find this plan.
        $engine = app(PlanFinancialEngine::class);
        $weightedOnly = $engine->analyze(20000, 21, 21, [
            ['media_tier_id' => 1, 'photo_maximum' => 21, 'video_maximum' => 21],
            ['media_tier_id' => 2, 'photo_maximum' => 1, 'video_maximum' => 1],
        ]);
        $this->assertTrue($weightedOnly['profit_requirement_is_valid']);
        $this->assertLessThan(22000, $weightedOnly['max_catalog_value_minor']);
        $this->assertLessThan(9500, $recommended['distribution_fit_bps']);

        $keys = array_column($result['alternatives'], 'key');
        $this->assertContains('max_value', $keys);
        $this->assertContains('max_profit', $keys);
        foreach ($result['alternatives'] as $alternative) {
            $this->assertTrue($alternative['meets_value_target']);
            $this->assertTrue($alternative['analysis']['is_valid']);
        }
        $maxValue = collect($result['alternatives'])->firstWhere('key', 'max_value');
        $maxProfit = collect($result['alternatives'])->firstWhere('key', 'max_profit');
        $this->assertSame(35000, $maxValue['potential_cart_value_minor']);
        $this->assertGreaterThanOrEqual($recommended['worst_case_profit_minor'], $maxProfit['worst_case_profit_minor']);
    }

    public function test_optimizer_is_complete_against_every_quota_and_ceiling_combination(): void
    {
        $this->customTiers(
            ['photo' => [100, 80], 'video' => [200, 160]],
            ['photo' => [300, 150], 'video' => [500, 240]],
            ['photo' => [600, 250], 'video' => [900, 300]],
        );
        $this->configurePlanFinancialSettings(['minimum_profit_minor' => 1, 'minimum_margin_bps' => 1200]);
        $tiers = MediaTier::query()->orderBy('rank')->get();
        $engine = app(PlanFinancialEngine::class);

        foreach ([150, 300, 500] as $priceMinor) {
            $budget = $engine->profitBudget($priceMinor, $engine->activeSettings())['liability_budget_minor'];
            $maxPhotos = intdiv(max(0, $budget), 80);
            $maxVideos = intdiv(max(0, $budget), 160);
            $best = 0;
            $samples = [];
            $seen = 0;

            // Every shared total and every ceiling per tier, Basic included, that the engine accepts.
            foreach ($this->ceilingConfigurations($maxPhotos) as [$photos, $photoCeilings]) {
                foreach ($this->ceilingConfigurations($maxVideos) as [$videos, $videoCeilings]) {
                    $liability = $this->exposure($photos, $photoCeilings, [80, 150, 250])
                        + $this->exposure($videos, $videoCeilings, [160, 240, 300]);
                    $value = $this->exposure($photos, $photoCeilings, [100, 300, 600])
                        + $this->exposure($videos, $videoCeilings, [200, 500, 900]);
                    if ($liability <= $budget) {
                        $best = max($best, $value);
                    }
                    if ($seen++ % 97 === 0) {
                        $samples[] = [$photos, $videos, $photoCeilings, $videoCeilings, $liability, $value];
                    }
                }
            }

            $result = $this->recommend(number_format($priceMinor / 100, 2, '.', ''));
            $target = intdiv(($priceMinor * 11000) + 9999, 10000);
            $this->assertSame($best, $result['proof']['max_profit_safe_value_minor'], "price {$priceMinor}");
            $this->assertSame($best >= $target, $result['proof']['target_feasible_at_price'], "price {$priceMinor}");

            // The brute-force exposure mirrors the engine exactly.
            $this->assertNotEmpty($samples);
            foreach ($samples as [$photos, $videos, $photoCeilings, $videoCeilings, $liability, $value]) {
                $analysis = $engine->analyze($priceMinor, $photos, $videos, $tiers->values()->map(fn ($tier, $i) => [
                    'media_tier_id' => $tier->id,
                    'photo_maximum' => $photoCeilings[$i],
                    'video_maximum' => $videoCeilings[$i],
                ])->all());
                $this->assertSame($liability, $analysis['max_creator_liability_minor']);
                $this->assertSame($value, $analysis['max_catalog_value_minor']);
            }
        }
    }

    public function test_basic_ceiling_equals_each_total_and_higher_ceilings_stay_inside_it(): void
    {
        $this->customTiers(
            ['photo' => [200, 200], 'video' => [500, 500]],
            ['photo' => [2000, 1000], 'video' => [5000, 2500]],
        );
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 1,
            'minimum_margin_bps' => 1200,
            'tier_distribution' => ['1' => 6000, '2' => 4000],
        ]);

        $result = $this->recommend('200.00');
        $options = array_merge([$result['recommendation']], $result['alternatives']);

        $this->assertNotEmpty($result['alternatives']);
        foreach ($options as $option) {
            $limits = collect($option['tier_limits'])->keyBy('media_tier_id');
            $this->assertSame($option['photo_quota'], $limits[1]['photo_maximum'], $option['key']);
            $this->assertSame($option['video_quota'], $limits[1]['video_maximum'], $option['key']);
            foreach ($limits->except(1) as $limit) {
                $this->assertLessThanOrEqual($option['photo_quota'], $limit['photo_maximum']);
                $this->assertLessThanOrEqual($option['video_quota'], $limit['video_maximum']);
            }
        }
    }

    public function test_api_options_match_the_validation_endpoint_and_manual_overrides_are_revalidated(): void
    {
        $this->customTiers(
            ['photo' => [200, 200], 'video' => [500, 500]],
            ['photo' => [2000, 1000], 'video' => [5000, 2500]],
        );
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 1,
            'minimum_margin_bps' => 1200,
            'tier_distribution' => ['1' => 6000, '2' => 4000],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        Queue::fake();

        $result = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/plans/recommend', ['price' => 200])
            ->assertOk()
            ->json();
        $this->assertEquals(app(PlanRecommendationService::class)->recommend('200.00'), $result);

        foreach (array_merge([$result['recommendation']], $result['alternatives']) as $option) {
            $validation = $this->validatePlan($admin, $option, $option['recommendation_value_target_bps']);
            $this->assertTrue($validation['is_valid'], $option['key']);
            $this->assertSame($option['max_creator_liability_minor'], $validation['max_creator_liability_minor']);
            $this->assertSame($option['worst_case_profit_minor'], $validation['worst_case_profit_minor']);
            $this->assertSame($option['worst_case_margin_bps'], $validation['worst_case_margin_bps']);
            $this->assertSame($option['potential_cart_value_minor'], $validation['max_catalog_value_minor']);
        }

        $recommended = $result['recommendation'];

        // Raising the Premium video ceiling past the budget is rejected on profit.
        $overspent = $this->withCeiling($recommended, 2, 'video', 7, videoQuota: max(7, $recommended['video_quota']));
        $overspent['photo_quota'] = $recommended['photo_quota'] + 40;
        $overspent = $this->withCeiling($overspent, 1, 'photo', $overspent['photo_quota']);
        $validation = $this->validatePlan($admin, $overspent, 11000);
        $this->assertFalse($validation['is_valid']);
        $this->assertFalse($validation['profit_requirement_is_valid']);

        // Removing Premium access keeps profit safe but falls below the 110% rule,
        // which stays in force for a recommendation-based plan.
        $lowered = $this->withCeiling($this->withCeiling($recommended, 2, 'photo', 0), 2, 'video', 0);
        $lowered['tier_limits'] = array_values(array_filter($lowered['tier_limits'], fn ($limit) => $limit['media_tier_id'] !== 2));
        $validation = $this->validatePlan($admin, $lowered, 11000);
        $this->assertFalse($validation['is_valid']);
        $this->assertTrue($validation['profit_requirement_is_valid']);
        $this->assertStringContainsString('below the recommendation target', implode(' ', $validation['errors']));
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/plans/create', $this->planPayload('Lowered', $lowered, 11000))
            ->assertStatus(422);
        $this->assertDatabaseMissing('licence_types', ['name' => 'Lowered']);

        // The recommended plan saves with its rule and keeps it for later sales.
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/plans/create', $this->planPayload('Recommended', $recommended, 11000))
            ->assertOk();
        $plan = LicenceType::query()->where('name', 'Recommended')->firstOrFail();
        $this->assertSame(11000, $plan->financial_analysis['customer_value_target_bps']);
        $saleAnalysis = app(PlanFinancialEngine::class)->analyzePlan($plan);
        $this->assertTrue($saleAnalysis['is_valid']);
        $this->assertSame(22000, $saleAnalysis['minimum_customer_value_minor']);
    }

    public function test_price_alternatives_are_exact_and_do_not_change_the_requested_price(): void
    {
        // 75% payout caps can reach 110% once a fixed $15 operating cost is spread
        // over a high enough price, but not at $200.
        $this->customTiers(
            ['photo' => [200, 150], 'video' => [500, 375]],
            ['photo' => [500, 375], 'video' => [1500, 1125]],
            ['photo' => [2000, 1500], 'video' => [5000, 3750]],
        );
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 1,
            'minimum_margin_bps' => 1200,
            'fixed_operating_cost_minor' => 1500,
        ]);

        $result = $this->recommend('200.00');

        $this->assertSame('target_not_met', $result['status']);
        $this->assertSame('200.00', $result['price']);
        $this->assertTrue($result['proof']['target_feasible_at_some_price']);
        $this->assertSame(30123, $result['proof']['guaranteed_target_price_minor']);
        $prices = collect($result['alternatives'])->where('key', 'price_alternative')->pluck('price_minor')->all();
        $this->assertSame([27700, 28000], $prices);
        foreach ($result['alternatives'] as $alternative) {
            if ($alternative['key'] !== 'price_alternative') {
                $this->assertSame(20000, $alternative['price_minor']);
                $this->assertFalse($alternative['requires_price_change']);

                continue;
            }
            $this->assertTrue($alternative['requires_price_change']);
            $this->assertTrue($alternative['meets_value_target']);
            $this->assertTrue($alternative['analysis']['is_valid']);
            $this->assertSame(intdiv(($alternative['price_minor'] * 11000) + 9999, 10000), $alternative['minimum_customer_value_minor']);
        }

        // $277 is the lowest whole-dollar price that meets both requirements.
        $this->assertSame('target_not_met', $this->recommend('276.00')['status']);
        $this->assertSame('target_met', $this->recommend('277.00')['status']);
    }

    public function test_price_without_any_profit_safe_credit_explains_the_minimum_price(): void
    {
        $this->configurePlanFinancialSettings(['minimum_profit_minor' => 1, 'minimum_margin_bps' => 1200]);

        $result = $this->recommend('1.00');

        $this->assertFalse($result['is_available']);
        $this->assertSame('no_profit_safe_quota', $result['failure_code']);
        $this->assertSame(88, $result['proof']['liability_budget_minor']);
        $this->assertSame(205, $result['proof']['minimum_price_for_one_credit_minor']);
        $this->assertSame([], $result['alternatives']);
    }

    public function test_api_response_matches_the_shared_frontend_fixture(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/fixtures/plan-recommendation-200.json')), true);

        foreach ($fixture['scenarios'] as $name => $scenario) {
            $this->refreshTiers($scenario['tiers']);
            PlanFinancialSetting::query()->delete();
            $this->configurePlanFinancialSettings($scenario['settings']);

            $result = $this->recommend($scenario['price']);
            $expected = $scenario['response'];

            $this->assertSame($expected['status'], $result['status'], $name);
            $this->assertSame($expected['is_available'], $result['is_available'], $name);
            foreach ($expected['proof'] as $field => $value) {
                $this->assertSame($value, $result['proof'][$field], "{$name}: proof.{$field}");
            }
            foreach (['recommendation', 'closest_safe_configuration'] as $slot) {
                $this->assertSame($expected[$slot], $result[$slot] === null ? null : $this->optionSubset($result[$slot]), "{$name}: {$slot}");
            }
            $this->assertSame($expected['alternatives'], array_map(fn ($option) => $this->optionSubset($option), $result['alternatives']), $name);
        }
    }

    private function recommend(string $price): array
    {
        return app(PlanRecommendationService::class)->recommend($price);
    }

    /**
     * @param  array<string, array{0: int, 1: int}>  ...$tiers  [max price, payout cap] per media, by rank
     */
    private function customTiers(array ...$tiers): void
    {
        $this->refreshTiers(array_map(fn ($tier, $index) => [
            'id' => $index + 1,
            'rank' => $index + 1,
            'name' => ['Basic', 'Pro', 'Premium'][$index] ?? 'Tier '.($index + 1),
            'max_price_photo' => $tier['photo'][0],
            'payout_cap_photo' => $tier['photo'][1],
            'max_price_video' => $tier['video'][0],
            'payout_cap_video' => $tier['video'][1],
        ], $tiers, array_keys($tiers)));
    }

    private function refreshTiers(array $tiers): void
    {
        MediaTier::withTrashed()->forceDelete();
        foreach ($tiers as $tier) {
            MediaTier::create([
                'id' => $tier['id'],
                'name' => $tier['name'],
                'rank' => $tier['rank'],
                'min_price_photo' => 0,
                'max_price_photo' => $tier['max_price_photo'] / 100,
                'payout_cap_photo' => $tier['payout_cap_photo'] / 100,
                'min_price_video' => 0,
                'max_price_video' => $tier['max_price_video'] / 100,
                'payout_cap_video' => $tier['payout_cap_video'] / 100,
                'is_plan_eligible' => true,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return \Generator<array{0: int, 1: array<int, int>}>
     */
    private function ceilingConfigurations(int $maxQuota): \Generator
    {
        for ($quota = 0; $quota <= $maxQuota; $quota++) {
            for ($basic = 0; $basic <= $quota; $basic++) {
                for ($pro = 0; $pro <= $quota; $pro++) {
                    for ($premium = 0; $premium <= $quota; $premium++) {
                        if ($quota === 0 || $basic + $pro + $premium >= $quota) {
                            yield [$quota, [$basic, $pro, $premium]];
                        }
                    }
                }
            }
        }
    }

    /**
     * Worst case inside shared totals: the customer fills the most expensive ceilings first.
     */
    private function exposure(int $total, array $ceilings, array $amounts): int
    {
        $order = array_keys($amounts);
        usort($order, fn ($a, $b) => $amounts[$b] <=> $amounts[$a]);
        $exposure = 0;
        foreach ($order as $index) {
            $quantity = min($total, $ceilings[$index]);
            $exposure += $quantity * $amounts[$index];
            $total -= $quantity;
        }

        return $exposure;
    }

    private function validatePlan(User $admin, array $option, ?int $targetBps): array
    {
        return $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/plans/validate', [
            'price' => $option['price_minor'] / 100,
            'photo_quota' => $option['photo_quota'],
            'video_quota' => $option['video_quota'],
            'tier_limits' => $option['tier_limits'],
            'recommendation_value_target_bps' => $targetBps,
        ])->assertOk()->json();
    }

    private function planPayload(string $name, array $option, ?int $targetBps): array
    {
        return [
            'name' => $name,
            'price' => $option['price_minor'] / 100,
            'photo_quota' => $option['photo_quota'],
            'video_quota' => $option['video_quota'],
            'tier_limits' => $option['tier_limits'],
            'recommendation_value_target_bps' => $targetBps,
            'is_active' => true,
        ];
    }

    private function withCeiling(array $option, int $tierId, string $media, int $maximum, ?int $videoQuota = null): array
    {
        if ($videoQuota !== null) {
            $option['video_quota'] = $videoQuota;
        }
        $found = false;
        foreach ($option['tier_limits'] as &$limit) {
            if ($limit['media_tier_id'] === $tierId) {
                $limit["{$media}_maximum"] = $maximum;
                $found = true;
            }
            if ($limit['media_tier_id'] === 1) {
                $limit['photo_maximum'] = $option['photo_quota'];
                $limit['video_maximum'] = $option['video_quota'];
            }
        }
        unset($limit);
        if (! $found) {
            $option['tier_limits'][] = ['media_tier_id' => $tierId, 'photo_maximum' => 0, 'video_maximum' => 0, "{$media}_maximum" => $maximum];
        }

        return $option;
    }

    private function optionSubset(array $option): array
    {
        return array_intersect_key($option, array_flip(self::OPTION_FIELDS));
    }
}
