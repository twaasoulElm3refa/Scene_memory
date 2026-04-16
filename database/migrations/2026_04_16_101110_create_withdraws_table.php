<?php

use App\Models\User;
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
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class,'user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignIdFor(User::class,'approved_by')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->decimal('amount',10,2)->default(0);
            $table->decimal('fee',10,2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->enum('status', ['pending', 'approved', 'rejected', 'processing', 'paid'])
                ->default('pending');
            $table->string('method')->nullable();
            $table->json('payment_details')->nullable();
            $table->string('reference')->nullable();
            $table->boolean('mail_sent')->default(false)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraws');
    }
};
