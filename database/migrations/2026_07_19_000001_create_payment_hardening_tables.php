<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('purchases')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('operation', 32);
                $table->string('method', 16);
                $table->string('status', 24)->default('pending');
                $table->bigInteger('amount_minor');
                $table->string('currency', 3);
                $table->string('idempotency_key', 128)->unique();
                $table->string('paypal_request_id', 128)->nullable()->unique();
                $table->string('paypal_order_id', 64)->nullable()->unique();
                $table->string('capture_id', 64)->nullable()->unique();
                $table->string('custom_id', 127)->nullable();
                $table->string('reference_id', 127)->nullable();
                $table->string('merchant_id', 127)->nullable();
                $table->json('gateway_response')->nullable();
                $table->boolean('purchase_granted')->default(false);
                $table->boolean('wallet_credited')->default(false);
                $table->timestamp('capture_requested_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'method', 'created_at']);
                $table->index(['user_id', 'operation']);
            });
        }

        if (! Schema::hasTable('entitlements')) {
            Schema::create('entitlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('media_id')->constrained('events_imges')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->string('source', 32)->default('purchase');
                $table->json('metadata')->nullable();
                $table->timestamp('granted_at');
                $table->timestamps();
                $table->unique(['user_id', 'media_id'], 'entitlements_user_media_unique');
                $table->index(['order_id', 'payment_id']);
            });

            // Preserve valid historical ownership without granting pending orders.
            DB::table('purchase_items')
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->where('purchases.status', 'completed')
                ->whereNotNull('purchases.user_id')
                ->whereNotNull('purchase_items.image_id')
                ->select([
                    'purchases.user_id',
                    'purchase_items.image_id as media_id',
                    'purchases.id as order_id',
                ])
                ->orderBy('purchases.id')
                ->chunk(250, function ($rows) {
                    $now = now();
                    $records = collect($rows)->map(fn ($row) => [
                        'user_id' => $row->user_id,
                        'media_id' => $row->media_id,
                        'order_id' => $row->order_id,
                        'payment_id' => null,
                        'source' => 'legacy_backfill',
                        'metadata' => null,
                        'granted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('entitlements')->insertOrIgnore($records);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('payments');
    }
};
