<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('rank')->unique(); // 1 = Basic, 2 = Pro, 3 = Premium, etc.
            $table->decimal('min_price_photo', 10, 2)->default(0);
            $table->decimal('max_price_photo', 10, 2);
            $table->decimal('min_price_video', 10, 2)->default(0);
            $table->decimal('max_price_video', 10, 2);
            $table->decimal('payout_cap_photo', 10, 2);
            $table->decimal('payout_cap_video', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_plan_eligible')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed initial default tiers (illustrative baseline configurable by admin)
        $now = now();
        DB::table('media_tiers')->insert([
            [
                'name' => 'basic',
                'rank' => 1,
                'min_price_photo' => 0.00,
                'max_price_photo' => 2.00,
                'min_price_video' => 0.00,
                'max_price_video' => 5.00,
                'payout_cap_photo' => 1.80,
                'payout_cap_video' => 4.50,
                'is_active' => true,
                'is_plan_eligible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'pro',
                'rank' => 2,
                'min_price_photo' => 2.01,
                'max_price_photo' => 5.00,
                'min_price_video' => 5.01,
                'max_price_video' => 15.00,
                'payout_cap_photo' => 4.50,
                'payout_cap_video' => 13.50,
                'is_active' => true,
                'is_plan_eligible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'premium',
                'rank' => 3,
                'min_price_photo' => 5.01,
                'max_price_photo' => 20.00,
                'min_price_video' => 15.01,
                'max_price_video' => 50.00,
                'payout_cap_photo' => 18.00,
                'payout_cap_video' => 45.00,
                'is_active' => true,
                'is_plan_eligible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('media_tiers');
    }
};
