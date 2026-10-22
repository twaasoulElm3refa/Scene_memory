<?php

namespace Tests\Feature\Api\Plans;

use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\User;
use App\Services\PlanFinancialValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFinancialValidationTest extends TestCase
{
    use RefreshDatabase;

    protected MediaTier $proTier;

    protected function setUp(): void
    {
        parent::setUp();

        MediaTier::withTrashed()->forceDelete();

        $this->proTier = MediaTier::create([
            'name' => 'Pro',
            'rank' => 2,
            'min_price_photo' => 2.01,
            'max_price_photo' => 5.00,
            'payout_cap_photo' => 3.50,
            'min_price_video' => 5.01,
            'max_price_video' => 15.00,
            'payout_cap_video' => 10.00,
            'is_plan_eligible' => true,
            'is_active' => true,
        ]);
    }

    public function test_financial_validator_approves_solvent_plan(): void
    {
        // 10 photos @ max $5.00 = $50.00
        // 2 videos @ max $15.00 = $30.00
        // Total max potential catalog value = $80.00
        // Plan price = $99.00 -> Valid!
        $result = PlanFinancialValidator::validatePlan(
            price: 99.00,
            tier: $this->proTier,
            photoQuota: 10,
            videoQuota: 2
        );

        $this->assertTrue($result['is_valid']);
        $this->assertTrue($result['catalog_value_is_valid']);
        $this->assertTrue($result['creator_liability_is_valid']);
        $this->assertEquals(80.00, $result['max_potential_catalog_value']);
        // Max liability: 10 * 3.50 + 2 * 10.00 = 35.00 + 20.00 = 55.00
        $this->assertEquals(55.00, $result['max_creator_liability']);
        $this->assertEquals(19.00, $result['difference']);
    }

    public function test_financial_validator_rejects_insolvent_plan(): void
    {
        // 10 photos @ max $5.00 = $50.00
        // 2 videos @ max $15.00 = $30.00
        // Total max potential catalog value = $80.00
        // Plan price = $60.00. Capped creator liability is covered, but the
        // original listing-price obligation is not and must still reject.
        $result = PlanFinancialValidator::validatePlan(
            price: 60.00,
            tier: $this->proTier,
            photoQuota: 10,
            videoQuota: 2
        );

        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['catalog_value_is_valid']);
        $this->assertTrue($result['creator_liability_is_valid']);
        $this->assertEquals(80.00, $result['max_potential_catalog_value']);
        $this->assertEquals(55.00, $result['max_creator_liability']);
        $this->assertEquals(-20.00, $result['difference']);
        $this->assertEquals(5.00, $result['creator_liability_headroom']);
        $this->assertStringContainsString('eligible listing value', $result['message']);
    }

    public function test_creator_payout_liability_is_validated_independently_from_listing_value(): void
    {
        $unsafeTier = MediaTier::create([
            'name' => 'Unsafe Imported Tier',
            'rank' => 3,
            'min_price_photo' => 5.01,
            'max_price_photo' => 6.00,
            'payout_cap_photo' => 8.00,
            'min_price_video' => 15.01,
            'max_price_video' => 20.00,
            'payout_cap_video' => 25.00,
            'is_plan_eligible' => true,
            'is_active' => true,
        ]);

        // 10 photos have $60 maximum listing value but $80 maximum payout liability.
        $result = PlanFinancialValidator::validatePlan(70.00, $unsafeTier, 10, 0);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue($result['catalog_value_is_valid']);
        $this->assertFalse($result['creator_liability_is_valid']);
        $this->assertEquals(60.00, $result['max_potential_catalog_value']);
        $this->assertEquals(80.00, $result['max_creator_liability']);
        $this->assertEquals(10.00, $result['catalog_price_headroom']);
        $this->assertEquals(-10.00, $result['creator_liability_headroom']);
        $this->assertStringContainsString('creator payout liability', $result['message']);
    }

    public function test_admin_api_rejects_insolvent_plan_creation_without_bypass(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Insolvent plan attempt via API: Price $30.00 vs Catalog Value $80.00
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/plans/create', [
            'name' => 'Insolvent Plan',
            'price' => 30.00,
            'media_tier_id' => $this->proTier->id,
            'photo_quota' => 10,
            'video_quota' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'status' => 'error',
        ]);
        $this->assertDatabaseMissing('licence_types', ['name' => 'Insolvent Plan']);
    }

    public function test_financial_validation_bypass_fields_are_strictly_prohibited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'name' => 'Bypass Attempt',
            'price' => 30.00,
            'media_tier_id' => $this->proTier->id,
            'photo_quota' => 10,
            'video_quota' => 2,
        ];

        foreach (['allow_subsidized', 'bypass_financial_check'] as $field) {
            $this->actingAs($admin, 'sanctum')->postJson('/api/v1/plans/create', $payload + [$field => true])
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseMissing('licence_types', ['name' => 'Bypass Attempt']);
    }

    public function test_plan_with_quotas_requires_a_media_tier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/plans/create', [
            'name' => 'Missing Tier',
            'price' => 100.00,
            'photo_quota' => 1,
            'video_quota' => 0,
        ])->assertUnprocessable()
            ->assertJsonPath('validation.metrics.is_valid', false);

        $this->assertDatabaseMissing('licence_types', ['name' => 'Missing Tier']);
    }

    public function test_tier_change_is_rolled_back_when_it_would_invalidate_an_existing_plan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        LicenceType::create([
            'name' => 'Covered Plan',
            'slug' => 'covered-plan',
            'price' => 80.00,
            'media_tier_id' => $this->proTier->id,
            'photo_quota' => 10,
            'video_quota' => 2,
        ]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/media-tiers', [
            'id' => $this->proTier->id,
            'name' => 'Pro',
            'rank' => 2,
            'min_photo_price' => 2.01,
            'max_photo_price' => 6.00,
            'max_photo_payout_cap' => 3.50,
            'min_video_price' => 5.01,
            'max_video_price' => 15.00,
            'max_video_payout_cap' => 10.00,
            'is_plan_eligible' => true,
            'is_active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('financial_validation');

        $this->assertEquals(5.00, (float) $this->proTier->fresh()->max_price_photo);
    }

    public function test_admin_validation_endpoint_returns_accurate_financial_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/plans/validate?'.http_build_query([
            'price' => 100.00,
            'media_tier_id' => $this->proTier->id,
            'photo_quota' => 10,
            'video_quota' => 2,
        ]));

        $response->assertOk();
        $response->assertJson([
            'is_valid' => true,
            'catalog_value_is_valid' => true,
            'creator_liability_is_valid' => true,
            'max_potential_catalog_value' => 80.00,
            'max_creator_liability' => 55.00,
            'difference' => 20.00,
            'catalog_price_headroom' => 20.00,
            'creator_liability_headroom' => 45.00,
        ]);
    }
}
