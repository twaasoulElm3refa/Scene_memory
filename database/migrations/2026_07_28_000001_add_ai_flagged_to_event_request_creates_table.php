<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_request_creates', function (Blueprint $table) {
            $table->boolean('ai_flagged')->default(false)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_request_creates', function (Blueprint $table) {
            $table->dropIndex(['ai_flagged']);
            $table->dropColumn('ai_flagged');
        });
    }
};
