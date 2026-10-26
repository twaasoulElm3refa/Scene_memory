<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holds reserve wallet money that PayPal refunded, reversed or put in dispute.
     * They never change balances or ledger history; they only reduce what can be
     * withdrawn until an administrator resolves them. Additive and reversible.
     */
    public function up(): void
    {
        Schema::create('financial_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('reason', 16);
            // PayPal refund, reversal or dispute id. Together with the payment and
            // wallet it makes every reversal idempotent across duplicate webhooks.
            $table->string('provider_reference', 128);
            $table->string('source_event_id', 128)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 16)->default('active');
            $table->json('metadata')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'wallet_id', 'reason', 'provider_reference'], 'financial_holds_idempotency_unique');
            $table->index(['wallet_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_holds');
    }
};
