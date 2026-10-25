<?php

namespace Tests\Feature\Api\Plans;

use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Services\PlanFinancialEngine;
use App\Services\PlanRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFinancialEngineTest extends TestCase
{
    use RefreshDatabase;

    private MediaTier $basic;

    private MediaTier $premium;

    protected function setUp(): void
    {
        parent::setUp();
        MediaTier::withTrashed()->forceDelete();
        $this->basic = MediaTier::create([
            'name' => 'Basic', 'rank' => 1,
            'min_price_photo' => 0, 'max_price_photo' => 2, 'payout_cap_photo' => 1,
            'min_price_video' => 0, 'max_price_video' => 4, 'payout_cap_video' => 2,
            'is_plan_eligible' => true, 'is_active' => true,
        ]);
        $this->premium = MediaTier::create([
            'name' => 'Premium', 'rank' => 2,
            'min_price_photo' => 2.01, 'max_price_photo' => 6, 'payout_cap_photo' => 5,
            'min_price_video' => 4.01, 'max_price_video' => 12, 'payout_cap_video' => 9,
            'is_plan_eligible' => true, 'is_active' => true,
        ]);
    }

    public function test_unknown_assumptions_fail_closed(): void
    {
        $result = app(PlanFinancialEngine::class)->analyze(3000, 1, 0, [[
            'media_tier_id' => $this->basic->id,
            'photo_maximum' => 1,
            'video_maximum' => 0,
        ]]);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('unconfigured', $result['financial_safety_status']);
        $this->assertContains('active_financial_settings', $result['missing_assumptions']);
        $this->assertNull($result['worst_case_profit_minor']);
    }

    public function test_worst_case_optimizer_uses_expensive_tier_first_and_all_costs(): void
    {
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 500,
            'minimum_margin_bps' => 2000,
            'operating_cost_bps' => 200,
            'fixed_operating_cost_minor' => 50,
            'risk_reserve_bps' => 100,
            'tax_bps' => 500,
        ])->paymentFees()->where('payment_method', 'paypal')->update([
            'percentage_bps' => 300,
            'fixed_fee_minor' => 30,
        ]);

        $result = app(PlanFinancialEngine::class)->analyze(3000, 5, 0, [
            ['media_tier_id' => $this->basic->id, 'photo_maximum' => 5, 'video_maximum' => 0],
            ['media_tier_id' => $this->premium->id, 'photo_maximum' => 2, 'video_maximum' => 0],
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(1300, $result['max_creator_liability_minor']);
        $this->assertSame(1800, $result['max_catalog_value_minor']);
        $this->assertSame('paypal', $result['payment_fee_method']);
        $this->assertSame(410, $result['total_non_payout_cost_minor']);
        $this->assertSame(1290, $result['worst_case_profit_minor']);
        $this->assertSame(600, $result['required_profit_minor']);
        $this->assertLessThanOrEqual(3000, $result['minimum_sustainable_price_minor']);
    }

    public function test_recommendation_is_deterministic_and_revalidates_as_safe(): void
    {
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 500,
            'minimum_margin_bps' => 1200,
            'operating_cost_bps' => 200,
            'risk_reserve_bps' => 100,
            'tier_distribution' => [
                (string) $this->basic->id => 6000,
                (string) $this->premium->id => 4000,
            ],
        ]);

        $service = app(PlanRecommendationService::class);
        $first = $service->recommend('200.00');
        $second = $service->recommend('200.00');

        $this->assertTrue($first['is_available']);
        $this->assertSame($first['photo_quota'], $second['photo_quota']);
        $this->assertSame($first['video_quota'], $second['video_quota']);
        $this->assertSame($first['tier_limits'], $second['tier_limits']);
        $this->assertTrue($first['analysis']['is_valid']);
        $this->assertGreaterThan(0, $first['photo_quota']);
        $this->assertGreaterThan(0, $first['video_quota']);
        $basicLimit = collect($first['tier_limits'])->firstWhere('media_tier_id', $this->basic->id);
        $premiumLimit = collect($first['tier_limits'])->firstWhere('media_tier_id', $this->premium->id);
        $this->assertSame($first['photo_quota'], $basicLimit['photo_maximum']);
        $this->assertSame($first['video_quota'], $basicLimit['video_maximum']);
        $this->assertLessThan($first['photo_quota'], $premiumLimit['photo_maximum']);
        $this->assertLessThan($first['video_quota'], $premiumLimit['video_maximum']);
        $this->assertGreaterThanOrEqual(22000, $first['potential_cart_value_minor']);
        $this->assertGreaterThanOrEqual(11000, $first['potential_cart_value_ratio_bps']);
        $this->assertTrue($first['analysis']['profit_requirement_is_valid']);
        $this->assertGreaterThanOrEqual(1200, $first['analysis']['worst_case_margin_bps']);

        $recalculated = app(PlanFinancialEngine::class)->analyze(
            20000,
            $first['photo_quota'],
            $first['video_quota'],
            $first['tier_limits'],
            minimumCustomerValueMinor: 22000,
            customerValueTargetBps: 11000,
        );
        $this->assertSame($first['analysis']['max_creator_liability_minor'], $recalculated['max_creator_liability_minor']);
        $this->assertSame($first['analysis']['worst_case_profit_minor'], $recalculated['worst_case_profit_minor']);
        $this->assertTrue($recalculated['is_valid']);

        $plan = LicenceType::create([
            'name' => 'Recommended Plan',
            'slug' => 'recommended-plan',
            'price' => '200.00',
            'media_tier_id' => $this->premium->id,
            'photo_quota' => $first['photo_quota'],
            'video_quota' => $first['video_quota'],
            'financial_analysis' => $first['analysis'],
            'is_active' => true,
        ]);
        $plan->tierLimits()->createMany($first['tier_limits']);
        $this->assertTrue(app(PlanFinancialEngine::class)->analyzePlan($plan)['is_valid']);
    }

    public function test_recommendation_explains_when_profit_and_customer_value_are_incompatible(): void
    {
        $this->basic->update(['payout_cap_photo' => 2, 'payout_cap_video' => 4]);
        $this->premium->update(['payout_cap_photo' => 6, 'payout_cap_video' => 12]);
        $this->configurePlanFinancialSettings([
            'minimum_profit_minor' => 1,
            'minimum_margin_bps' => 1200,
            'tier_distribution' => [
                (string) $this->basic->id => 6000,
                (string) $this->premium->id => 4000,
            ],
        ]);

        $result = app(PlanRecommendationService::class)->recommend('200.00');

        $this->assertFalse($result['is_available']);
        $this->assertSame('profit_and_customer_value_incompatible', $result['failure_code']);
        $this->assertStringContainsString('10% customer-value target', $result['reason']);
        $this->assertLessThan(22000, $result['analysis']['max_catalog_value_minor']);
        $this->assertTrue($result['analysis']['profit_requirement_is_valid']);
    }
}
