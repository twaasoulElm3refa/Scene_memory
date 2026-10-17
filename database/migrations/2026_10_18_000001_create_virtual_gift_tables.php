<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_gifts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
        Schema::create('gift_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->string('request_hash', 64);
            // Historical identifiers deliberately have no cascading foreign keys.
            foreach (['sender_user_id', 'recipient_user_id', 'event_id', 'gift_id', 'payment_id'] as $column) {
                $table->unsignedBigInteger($column)->index();
            }
            $table->unique('payment_id');
            $table->unsignedBigInteger('comment_id')->nullable()->index();
            $table->string('target_type', 16);
            $table->unsignedInteger('quantity');
            $table->json('gift_snapshot');
            foreach (['unit_price_minor', 'gross_amount_minor', 'platform_commission_minor', 'recipient_earning_minor'] as $column) {
                $table->unsignedBigInteger($column);
            }
            $table->unsignedBigInteger('payment_processing_fee_minor')->nullable();
            $table->string('processing_fee_currency', 3)->nullable();
            $table->unsignedTinyInteger('processing_fee_precision')->nullable();
            $table->string('processing_fee_status', 16)->default('unknown');
            $table->string('currency', 3);
            $table->string('payment_method', 16);
            $table->string('payment_status', 24)->default('pending');
            $table->string('delivery_status', 24)->default('pending');
            $table->string('settlement_status', 24)->default('pending');
            $table->string('paypal_order_id', 64)->nullable()->unique();
            $table->string('paypal_capture_id', 64)->nullable()->unique();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->unique();
            $table->unsignedBigInteger('refunded_gross_minor')->default(0);
            $table->unsignedBigInteger('reversed_commission_minor')->default(0);
            $table->unsignedBigInteger('reversed_earning_minor')->default(0);
            $table->boolean('review_required')->default(false);
            $table->boolean('disputed')->default(false);
            $table->string('refund_request_id', 100)->nullable();
            $table->string('partial_refund_marker', 64)->nullable();
            $table->unsignedBigInteger('refund_requested_minor')->nullable();
            foreach (['paid_at', 'delivered_at', 'release_at', 'settled_at', 'last_reconciled_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->timestamps();
            $table->index(['payment_status', 'payment_method', 'created_at']);
            $table->index(['recipient_user_id', 'currency', 'settlement_status'], 'gift_recipient_currency_settlement_idx');
            $table->foreign('gift_id')->references('id')->on('virtual_gifts')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
        Schema::create('gift_earning_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gift_transaction_id')->unique();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('currency', 3);
            foreach (['gross_minor', 'commission_minor', 'recipient_share_minor', 'pending_minor', 'available_minor', 'reserved_minor', 'settled_minor', 'reversed_minor', 'recovery_minor'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            $table->string('status', 24)->default('pending');
            $table->timestamp('release_at')->index();
            $table->timestamps();
            $table->foreign('gift_transaction_id')->references('id')->on('gift_transactions')->restrictOnDelete();
            $table->index(['recipient_user_id', 'currency']);
        });
        Schema::create('gift_transaction_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gift_transaction_id')->index();
            $table->string('event_key', 160)->unique();
            $table->string('type', 40);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('gift_transaction_id')->references('id')->on('gift_transactions')->restrictOnDelete();
        });
        Schema::create('gift_earning_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('earning_entry_id')->index();
            $table->string('idempotency_key', 160)->unique();
            $table->string('type', 32);
            foreach (['pending', 'available', 'reserved', 'settled', 'reversed', 'recovery'] as $bucket) {
                $table->bigInteger($bucket.'_delta')->default(0);
            }
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('earning_entry_id')->references('id')->on('gift_earning_entries')->restrictOnDelete();
        });
        Schema::create('gift_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 24)->default('pending');
            $table->text('payment_details'); // Encrypted by Eloquent; never public.
            $table->string('payout_reference', 160)->nullable()->unique();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('gift_withdrawal_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('withdrawal_id')->index();
            $table->unsignedBigInteger('earning_entry_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unique(['withdrawal_id', 'earning_entry_id'], 'gift_payout_allocation_unique');
            $table->foreign('withdrawal_id')->references('id')->on('gift_withdrawals')->restrictOnDelete();
            $table->foreign('earning_entry_id')->references('id')->on('gift_earning_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Financial history is intentionally retained. Disable the feature on rollback.
    }
};
