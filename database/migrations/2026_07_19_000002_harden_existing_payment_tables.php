<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->hardenOrders();
        $this->hardenOrderItems();
        $this->hardenWallets();
        $this->hardenWalletTransactions();
        $this->hardenWebhookEvents();
    }

    private function hardenOrderItems(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        if (! Schema::hasColumn('purchase_items', 'snapshot')) {
            Schema::table('purchase_items', fn (Blueprint $table) => $table->json('snapshot')->nullable()->after('purchased_type'));
        }
    }

    private function hardenOrders(): void
    {
        if (! Schema::hasTable('purchases')) {
            return;
        }

        $columns = [
            'order_type' => fn (Blueprint $table) => $table->string('order_type', 32)->nullable()->after('type'),
            'amount_minor' => fn (Blueprint $table) => $table->bigInteger('amount_minor')->nullable()->after('amount'),
            'snapshot_hash' => fn (Blueprint $table) => $table->string('snapshot_hash', 64)->nullable()->after('idempotency_key'),
            'purchase_granted' => fn (Blueprint $table) => $table->boolean('purchase_granted')->default(false)->after('wallet_credited'),
            'fulfilled_at' => fn (Blueprint $table) => $table->timestamp('fulfilled_at')->nullable()->after('paid_at'),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('purchases', $name)) {
                Schema::table('purchases', $definition);
            }
        }

        DB::table('purchases')->whereNull('amount_minor')->orderBy('id')->chunkById(250, function ($orders) {
            foreach ($orders as $order) {
                DB::table('purchases')->where('id', $order->id)->update([
                    'amount_minor' => $this->decimalToMinor($order->amount),
                ]);
            }
        });

        DB::table('purchases')
            ->where('status', 'completed')
            ->where('type', 'checkout')
            ->update(['purchase_granted' => true, 'fulfilled_at' => DB::raw('COALESCE(fulfilled_at, paid_at)')]);

        if (Schema::hasTable('payments')) {
            DB::table('purchases')
                ->whereNotNull('user_id')
                ->whereIn('type', ['checkout', 'wallet_deposit'])
                ->orderBy('id')
                ->chunkById(250, function ($orders) {
                    foreach ($orders as $order) {
                        if (DB::table('payments')->where('order_id', $order->id)->exists()) {
                            continue;
                        }

                        $operation = $order->type === 'wallet_deposit' ? 'wallet_deposit' : 'purchase';
                        DB::table('payments')->insert([
                            'order_id' => $order->id,
                            'user_id' => $order->user_id,
                            'operation' => $operation,
                            'method' => $order->payment_method === 'wallet' ? 'wallet' : 'paypal',
                            'status' => $order->status ?: 'pending',
                            'amount_minor' => $order->amount_minor,
                            'currency' => strtoupper((string) ($order->currency ?: 'USD')),
                            'idempotency_key' => hash('sha256', 'legacy-payment|'.$order->id),
                            'paypal_request_id' => null,
                            'paypal_order_id' => $order->paypal_order_id,
                            'capture_id' => $order->transaction_id,
                            'custom_id' => ($operation === 'wallet_deposit' ? 'wallet_topup:' : 'checkout:').$order->id,
                            'reference_id' => (string) $order->id,
                            'merchant_id' => null,
                            'gateway_response' => $order->gateway_response,
                            'purchase_granted' => (bool) ($order->purchase_granted ?? false),
                            'wallet_credited' => (bool) ($order->wallet_credited ?? false),
                            'capture_requested_at' => $order->capture_requested_at,
                            'paid_at' => $order->paid_at,
                            'fulfilled_at' => $order->fulfilled_at,
                            'created_at' => $order->created_at,
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    private function hardenWallets(): void
    {
        if (! Schema::hasTable('wallets')) {
            return;
        }

        if (! Schema::hasColumn('wallets', 'balance_minor')) {
            Schema::table('wallets', fn (Blueprint $table) => $table->bigInteger('balance_minor')->nullable()->after('amount'));
        }

        DB::table('wallets')->whereNull('balance_minor')->orderBy('id')->chunkById(250, function ($wallets) {
            foreach ($wallets as $wallet) {
                DB::table('wallets')->where('id', $wallet->id)->update([
                    'balance_minor' => $this->decimalToMinor($wallet->amount),
                ]);
            }
        });

        $this->addUniqueIndexIfSafe('wallets', ['user_id'], 'wallets_user_id_unique');
    }

    private function hardenWalletTransactions(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        $columns = [
            'payment_id' => fn (Blueprint $table) => $table->foreignId('payment_id')->nullable()->after('purchase_id')->constrained('payments')->nullOnDelete(),
            'source' => fn (Blueprint $table) => $table->string('source', 32)->nullable()->after('type'),
            'amount_minor' => fn (Blueprint $table) => $table->bigInteger('amount_minor')->nullable()->after('amount'),
            'balance_before_minor' => fn (Blueprint $table) => $table->bigInteger('balance_before_minor')->nullable()->after('balance_before'),
            'balance_after_minor' => fn (Blueprint $table) => $table->bigInteger('balance_after_minor')->nullable()->after('balance_after'),
            'idempotency_key' => fn (Blueprint $table) => $table->string('idempotency_key', 160)->nullable(),
            'reference' => fn (Blueprint $table) => $table->string('reference', 160)->nullable(),
            'slug' => fn (Blueprint $table) => $table->string('slug', 160)->nullable(),
            'metadata' => fn (Blueprint $table) => $table->json('metadata')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('wallet_transactions', $name)) {
                Schema::table('wallet_transactions', $definition);
            }
        }

        $this->addUniqueIndexIfSafe('wallet_transactions', ['idempotency_key'], 'wallet_transactions_idempotency_unique');
        $this->addUniqueIndexIfSafe('wallet_transactions', ['slug'], 'wallet_transactions_slug_unique');
        $this->addUniqueIndexIfSafe('wallet_transactions', ['payment_id'], 'wallet_transactions_payment_id_unique');
    }

    private function hardenWebhookEvents(): void
    {
        if (! Schema::hasTable('paypal_webhook_events')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE paypal_webhook_events MODIFY status VARCHAR(20) NOT NULL DEFAULT 'received'");
        }

        $columns = [
            'payment_id' => fn (Blueprint $table) => $table->foreignId('payment_id')->nullable()->after('event_type')->constrained('payments')->nullOnDelete(),
            'paypal_order_id' => fn (Blueprint $table) => $table->string('paypal_order_id', 64)->nullable(),
            'capture_id' => fn (Blueprint $table) => $table->string('capture_id', 64)->nullable(),
            'payload' => fn (Blueprint $table) => $table->json('payload')->nullable(),
            'error_message' => fn (Blueprint $table) => $table->string('error_message', 500)->nullable(),
            'received_at' => fn (Blueprint $table) => $table->timestamp('received_at')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('paypal_webhook_events', $name)) {
                Schema::table('paypal_webhook_events', $definition);
            }
        }
    }

    private function addUniqueIndexIfSafe(string $table, array $columns, string $name): void
    {
        $indexes = collect(Schema::getIndexes($table))->pluck('name');
        if ($indexes->contains($name)) {
            return;
        }

        $column = $columns[0];
        $duplicates = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1');
        $hasDuplicates = DB::query()->fromSub($duplicates, 'duplicate_values')->exists();

        if (! $hasDuplicates) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
        }
    }

    private function decimalToMinor(mixed $value): int
    {
        $normalized = trim((string) ($value ?? '0'));
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $minor = ((int) ($whole === '' ? '0' : $whole) * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$minor : $minor;
    }

    public function down(): void
    {
        // Intentionally non-destructive: financial hardening columns and ledger history are retained.
    }
};
