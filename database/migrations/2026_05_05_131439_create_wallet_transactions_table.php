<?php

use App\Models\purchases;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
              $table->foreignIdFor(User::class,'user_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Wallet::class,'wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(purchases::class,'purchase_id')->constrained()->cascadeOnDelete();
            $table->integer('amount')->default(0);
            $table->enum('type',['credit','debit'])->default('credit');
            $table->string('description')->nullable();
            $table->integer('balance_before')->default(0);
            $table->integer('balance_after')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['wallet_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
