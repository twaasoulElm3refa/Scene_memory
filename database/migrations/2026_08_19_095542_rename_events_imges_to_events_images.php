<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('events_imges') &&
            !Schema::hasTable('events_images')
        ) {
            Schema::rename('events_imges', 'events_images');
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('events_images') &&
            !Schema::hasTable('events_imges')
        ) {
            Schema::rename('events_images', 'events_imges');
        }
    }
};
