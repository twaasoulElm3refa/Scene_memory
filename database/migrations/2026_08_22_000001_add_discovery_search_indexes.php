<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_images', function (Blueprint $table) {
            $table->index(
                ['type', 'is_active', 'event_id'],
                'events_images_discovery_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('events_images', function (Blueprint $table) {
            $table->dropIndex('events_images_discovery_index');
        });
    }
};
