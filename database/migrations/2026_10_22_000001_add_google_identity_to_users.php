<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique();
            $table->text('google_avatar_url')->nullable();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep password nullable: reverting it would invalidate passwordless accounts.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'google_avatar_url']);
        });
    }
};
