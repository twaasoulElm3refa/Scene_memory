<?php

use App\Services\WeeklyCompetitionPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This row also records empty weeks and serializes awards with finalization.
        Schema::create('competition_weeks', function (Blueprint $table) {
            $table->date('week_start')->primary();
            $table->date('week_end');
            $table->string('timezone', 64);
            $table->timestamp('finalized_at')->nullable();
            $table->index(['finalized_at', 'week_start']);
        });

        Schema::create('user_weekly_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            $table->bigInteger('points')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
            $table->index(['week_start', 'points', 'user_id']);
        });

        Schema::create('weekly_leaderboards', function (Blueprint $table) {
            $table->id();
            // Retain finalized positions even if an account is later deleted.
            $table->unsignedBigInteger('user_id');
            $table->date('week_start');
            $table->date('week_end');
            $table->bigInteger('points');
            $table->unsignedInteger('rank');
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
            $table->unique(['week_start', 'rank']);
        });

        $period = app(WeeklyCompetitionPeriod::class)->metadata();
        DB::table('competition_weeks')->insert([
            'week_start' => $period['week_start'],
            'week_end' => $period['week_end'],
            'timezone' => $period['timezone'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_leaderboards');
        Schema::dropIfExists('user_weekly_points');
        Schema::dropIfExists('competition_weeks');
    }
};
