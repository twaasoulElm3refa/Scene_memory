<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_withdrawals', function (Blueprint $table) {
            $table->unsignedBigInteger('available_balance_at_request_minor')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('failed_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Gift withdrawal review history cannot be rolled back safely.');
    }
};
