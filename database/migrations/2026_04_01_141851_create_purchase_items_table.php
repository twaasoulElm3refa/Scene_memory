<?php

use App\Models\eventsImges;
use App\Models\purchases;
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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(purchases::class,'purchase_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(eventsImges::class,'image_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price',10,2)->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
