<?php

use App\Models\licenceType;
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
        Schema::create('plan_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(licenceType::class,'plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('feature')->nullable();
            $table->boolean('is_active')->default(true)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_benefits');
    }
};
