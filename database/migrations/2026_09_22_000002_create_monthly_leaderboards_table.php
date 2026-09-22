<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->integer('points');
            $table->unsignedInteger('rank');
            $table->timestamps();

            $table->unique(
                ['user_id', 'month', 'year'],
                'monthly_leaderboards_user_month_year_unique'
            );
            $table->index(['year', 'month', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_leaderboards');
    }
};
