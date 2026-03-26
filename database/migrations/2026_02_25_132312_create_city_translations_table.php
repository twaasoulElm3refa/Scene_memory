<?php

use App\Models\Cities;
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
        Schema::create('city_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Cities::class,'city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['city_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_translations');
    }
};
