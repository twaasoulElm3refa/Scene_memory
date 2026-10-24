<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_identifier', 128)->index();
            $table->string('masked_email', 255)->nullable();
            $table->string('request_type', 50)->default('full_account_deletion');
            $table->string('status', 50)->default('pending_confirmation')->index();
            $table->text('reason')->nullable();
            $table->boolean('requires_code')->default(false);
            $table->string('confirmation_code_hash', 255)->nullable();
            $table->timestamp('confirmation_code_expires_at')->nullable();
            $table->unsignedInteger('confirmation_code_attempts')->default(0);
            $table->boolean('financial_obligations_detected')->default(false);
            $table->text('financial_hold_reason')->nullable();
            $table->json('retention_policy_applied')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_data_deletion_requests');
    }
};
