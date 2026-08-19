<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('events_imges', 'events_images');
    }

    public function down(): void
    {
        Schema::rename('events_images', 'events_imges');
    }
};
