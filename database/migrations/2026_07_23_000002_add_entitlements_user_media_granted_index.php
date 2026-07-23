<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entitlements')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('entitlements'))->pluck('name');
        if ($indexes->contains('entitlements_user_media_granted_at_index')) {
            return;
        }

        Schema::table('entitlements', function (Blueprint $table) {
            $table->index(['user_id', 'media_id', 'granted_at'], 'entitlements_user_media_granted_at_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('entitlements')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('entitlements'))->pluck('name');
        if (! $indexes->contains('entitlements_user_media_granted_at_index')) {
            return;
        }

        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropIndex('entitlements_user_media_granted_at_index');
        });
    }
};
