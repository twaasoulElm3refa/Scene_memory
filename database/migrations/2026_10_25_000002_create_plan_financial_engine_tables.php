<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_financial_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('currency', 3);
            $table->unsignedBigInteger('minimum_profit_minor')->nullable();
            $table->unsignedInteger('minimum_margin_bps')->nullable();
            $table->unsignedInteger('operating_cost_bps')->nullable();
            $table->unsignedBigInteger('fixed_operating_cost_minor')->nullable();
            $table->unsignedInteger('risk_reserve_bps')->nullable();
            $table->unsignedInteger('tax_bps')->nullable();
            $table->unsignedInteger('photo_allocation_bps')->nullable();
            $table->json('tier_distribution')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plan_payment_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_financial_setting_id')
                ->constrained('plan_financial_settings')->cascadeOnDelete();
            $table->string('payment_method', 30);
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->unsignedBigInteger('fixed_fee_minor')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['plan_financial_setting_id', 'payment_method'], 'plan_fee_setting_method_unique');
        });

        Schema::create('plan_tier_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licence_id')->constrained('licence_types')->cascadeOnDelete();
            $table->foreignId('media_tier_id')->constrained('media_tiers')->restrictOnDelete();
            $table->unsignedInteger('photo_maximum')->default(0);
            $table->unsignedInteger('video_maximum')->default(0);
            $table->timestamps();

            $table->unique(['licence_id', 'media_tier_id']);
        });

        Schema::table('licence_types', function (Blueprint $table) {
            $table->string('financial_status', 30)->default('unconfigured')->after('max_accumulated_videos');
            $table->unsignedInteger('financial_settings_version')->nullable()->after('financial_status');
            $table->json('financial_analysis')->nullable()->after('financial_settings_version');
            $table->timestamp('financially_validated_at')->nullable()->after('financial_analysis');
        });

        Schema::table('user_credit_grants', function (Blueprint $table) {
            $table->json('tier_limits_snapshot')->nullable()->after('currency');
            $table->json('tier_usage_snapshot')->nullable()->after('tier_limits_snapshot');
            $table->json('financial_assumptions_snapshot')->nullable()->after('tier_usage_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('user_credit_grants', function (Blueprint $table) {
            $table->dropColumn([
                'tier_limits_snapshot',
                'tier_usage_snapshot',
                'financial_assumptions_snapshot',
            ]);
        });

        Schema::table('licence_types', function (Blueprint $table) {
            $table->dropColumn([
                'financial_status',
                'financial_settings_version',
                'financial_analysis',
                'financially_validated_at',
            ]);
        });

        Schema::dropIfExists('plan_tier_limits');
        Schema::dropIfExists('plan_payment_fee_rules');
        Schema::dropIfExists('plan_financial_settings');
    }
};
