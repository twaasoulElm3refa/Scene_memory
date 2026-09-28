<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_coverage_requests', function (Blueprint $table) {
            $table->time('coverage_time')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('special_coverage_requests', function (Blueprint $table) {
            $table->dropColumn('coverage_time');
        });
    }
};
