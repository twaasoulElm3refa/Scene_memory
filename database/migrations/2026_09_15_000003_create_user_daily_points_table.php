<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('action');
            $table->unsignedInteger('count')->default(0);
            $table->integer('points')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date', 'action'], 'user_daily_points_user_date_action_unique');
            $table->index(['date', 'points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_points');
    }
};
