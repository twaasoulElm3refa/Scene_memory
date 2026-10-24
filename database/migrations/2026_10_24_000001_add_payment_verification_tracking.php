<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('verification_attempts')->default(0);
            $table->timestamp('last_verification_at')->nullable();
            $table->timestamp('next_verification_at')->nullable();
            $table->timestamp('verification_lease_until')->nullable();
            $table->string('verification_error_category', 40)->nullable();
            $table->boolean('verification_review_required')->default(false);
            $table->index(['method', 'status', 'verification_review_required', 'next_verification_at'], 'payments_verification_due_index');
        });
    }

    public function down(): void
    {
        // Financial verification history is intentionally retained on rollback.
    }
};
