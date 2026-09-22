<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_monthly_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->integer('points')->default(0);
            $table->timestamps();

            $table->unique(
                ['user_id', 'month', 'year'],
                'user_monthly_points_user_month_year_unique'
            );
            $table->index(['year', 'month', 'points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_monthly_points');
    }
};
