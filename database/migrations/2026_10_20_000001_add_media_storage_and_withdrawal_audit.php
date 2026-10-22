<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_images', function (Blueprint $table) {
            // Legacy records stay on their existing disk until an operator migrates them.
            $table->string('original_disk', 32)->default('public');
            $table->string('preview_disk', 32)->default('public');
        });
        Schema::create('wallet_reservation_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id')->index();
            $table->unsignedBigInteger('withdrawal_id')->index();
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 32);
            $table->bigInteger('balance_before_minor');
            $table->bigInteger('balance_after_minor');
            $table->bigInteger('pending_before_minor');
            $table->bigInteger('pending_after_minor');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // A code rollback must not discard audit history or make private media public.
        // Throwing also preserves Laravel's migration record rather than falsely
        // reporting that these retained columns were removed.
        throw new RuntimeException('Security schema rollback requires the reviewed storage/financial rollback procedure. Preserve the additive columns and audit history.');
    }
};
