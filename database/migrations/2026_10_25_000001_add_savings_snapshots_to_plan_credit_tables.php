<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_credit_grants', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_price_paid_minor')->nullable()->after('payment_reference');
            $table->string('currency', 3)->nullable()->after('plan_price_paid_minor');
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('cart_value_minor')->nullable()->after('quantity');
            $table->string('currency', 3)->nullable()->after('cart_value_minor');
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn(['cart_value_minor', 'currency']);
        });

        Schema::table('user_credit_grants', function (Blueprint $table) {
            $table->dropColumn(['plan_price_paid_minor', 'currency']);
        });
    }
};
