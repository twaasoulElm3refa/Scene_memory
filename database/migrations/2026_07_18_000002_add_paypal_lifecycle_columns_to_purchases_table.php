<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->timestamp('capture_requested_at')->nullable()->after('paid_at');
            $table->timestamp('refunded_at')->nullable()->after('capture_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['capture_requested_at', 'refunded_at']);
        });
    }
};
