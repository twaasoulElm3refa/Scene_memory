<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events_images', function (Blueprint $table) {
            $table->string('source_fingerprint', 64)->nullable()->index();
            $table->string('local_original_path')->nullable();
        });
        Schema::table('cloudinary_media_assets', function (Blueprint $table) {
            $table->foreignId('event_media_id')->nullable()->index();
            $table->string('delivery_type', 20)->default('upload');
            $table->timestamp('uploaded_at')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('backup_disk')->nullable();
            $table->string('backup_key')->nullable();
            $table->string('backup_status', 24)->default('pending');
            $table->string('backup_sha256', 64)->nullable();
            $table->timestamp('backup_verified_at')->nullable();
            $table->string('cleanup_status', 24)->default('retained');
            $table->string('cleanup_block_reason', 64)->nullable();
            $table->timestamp('cleanup_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cloudinary_media_assets', function (Blueprint $table) {
            $table->dropColumn(['event_media_id', 'delivery_type', 'uploaded_at', 'sha256', 'backup_disk', 'backup_key',
                'backup_status', 'backup_sha256', 'backup_verified_at', 'cleanup_status', 'cleanup_block_reason', 'cleanup_completed_at']);
        });
        Schema::table('events_images', function (Blueprint $table) {
            $table->dropIndex(['source_fingerprint']);
            $table->dropColumn(['source_fingerprint', 'local_original_path']);
        });
    }
};
