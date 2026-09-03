<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_coverage_requests', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('event_description')
                ->constrained('countries')
                ->nullOnDelete();
            $table->foreignId('city_id')
                ->nullable()
                ->after('country_id')
                ->constrained('cities')
                ->nullOnDelete();
            $table->date('start_date')->nullable()->after('city_id')->index();
            $table->string('event_type', 20)->nullable()->after('start_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('special_coverage_requests', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['country_id']);
            $table->dropIndex(['start_date']);
            $table->dropIndex(['event_type']);
            $table->dropColumn(['country_id', 'city_id', 'start_date', 'event_type']);
        });
    }
};
