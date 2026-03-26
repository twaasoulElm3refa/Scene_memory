<?php

use App\Models\PlanBenefits;
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
        Schema::create('benefits_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PlanBenefits::class,'benefit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('locale')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->unique(['benefit_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefits_translations');
    }
};
