<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cloudinary_media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('local_path')->nullable();
            $table->string('asset_id')->nullable();
            $table->string('public_id');
            $table->string('resource_type', 16);
            $table->string('format', 20);
            $table->unsignedBigInteger('version')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration', 10, 3)->nullable();
            $table->string('status', 20)->default('ready');
            $table->timestamps();
        });
        Schema::table('events_images', function (Blueprint $table) {
            $table->decimal('duration_seconds', 10, 3)->nullable();
            $table->string('media_format', 20)->nullable();
            $table->unsignedBigInteger('media_bytes')->nullable();
            $table->string('media_status', 20)->nullable();
            $table->string('fallback_preview_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events_images', function (Blueprint $table) {
            $table->dropColumn(['duration_seconds', 'media_format', 'media_bytes', 'media_status', 'fallback_preview_url']);
        });
        Schema::dropIfExists('cloudinary_media_assets');
    }
};
