<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_points_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->integer('points');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['user_id', 'action', 'reference_type', 'reference_id'],
                'user_points_history_award_unique'
            );
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_points_history');
    }
};
