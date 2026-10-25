<?php

namespace Tests;

use App\Models\MediaTier;
use App\Models\PlanFinancialSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledViews = sys_get_temp_dir().'/scemory-compiled-views';

        if (! is_dir($compiledViews)) {
            mkdir($compiledViews, 0777, true);
        }

        config()->set('view.compiled', $compiledViews);
        Storage::fake('media');
        Http::preventStrayRequests();
    }

    protected function configurePlanFinancialSettings(array $overrides = []): PlanFinancialSetting
    {
        $tiers = MediaTier::query()->where('is_active', true)->where('is_plan_eligible', true)->orderBy('rank')->get();
        $base = $tiers->isEmpty() ? 0 : intdiv(10000, $tiers->count());
        $remainder = 10000 - ($base * $tiers->count());
        $distribution = [];
        foreach ($tiers as $index => $tier) {
            $distribution[(string) $tier->id] = $base + ($index === 0 ? $remainder : 0);
        }

        $settings = PlanFinancialSetting::create(array_merge([
            'version' => 1,
            'currency' => 'USD',
            'minimum_profit_minor' => 1,
            'minimum_margin_bps' => 0,
            'operating_cost_bps' => 0,
            'fixed_operating_cost_minor' => 0,
            'risk_reserve_bps' => 0,
            'tax_bps' => 0,
            'photo_allocation_bps' => 5000,
            'tier_distribution' => $distribution,
            'is_active' => true,
        ], $overrides));
        foreach (['wallet', 'paypal'] as $method) {
            $settings->paymentFees()->create([
                'payment_method' => $method,
                'percentage_bps' => 0,
                'fixed_fee_minor' => 0,
                'is_enabled' => true,
            ]);
        }

        return $settings->load('paymentFees');
    }
}
