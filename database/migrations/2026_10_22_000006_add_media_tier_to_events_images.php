<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = Schema::hasTable('events_images') ? 'events_images' : 'events_imges';

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreignId('media_tier_id')->nullable()->after('price')->constrained('media_tiers')->nullOnDelete();
            $table->boolean('is_plan_redeemable')->default(true)->after('media_tier_id');
        });
    }

    public function down(): void
    {
        $tableName = Schema::hasTable('events_images') ? 'events_images' : 'events_imges';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['media_tier_id']);
            $table->dropColumn(['media_tier_id', 'is_plan_redeemable']);
        });
    }
};
