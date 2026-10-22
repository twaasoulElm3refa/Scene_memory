<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licence_types', function (Blueprint $table) {
            $table->foreignId('media_tier_id')->nullable()->after('price')->constrained('media_tiers')->nullOnDelete();
            $table->unsignedInteger('photo_quota')->default(0)->after('media_tier_id');
            $table->unsignedInteger('video_quota')->default(0)->after('photo_quota');
            $table->unsignedInteger('max_accumulated_photos')->default(1000)->after('video_quota');
            $table->unsignedInteger('max_accumulated_videos')->default(500)->after('max_accumulated_photos');
        });
    }

    public function down(): void
    {
        Schema::table('licence_types', function (Blueprint $table) {
            $table->dropForeign(['media_tier_id']);
            $table->dropColumn([
                'media_tier_id',
                'photo_quota',
                'video_quota',
                'max_accumulated_photos',
                'max_accumulated_videos',
            ]);
        });
    }
};
