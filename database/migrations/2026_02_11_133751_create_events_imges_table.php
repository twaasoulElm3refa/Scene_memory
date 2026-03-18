<?php

use App\Models\Events;
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
        Schema::create('events_imges', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Events::class,'event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('url')->nullable();
            $table->string('is_active')->default(false)->nullable();
            $table->enum('licence_type',['free', 'plan1', 'plan2'])->default('free')->nullable();
            $table->decimal('price',10,2)->default(0)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events_imges');
    }
};
