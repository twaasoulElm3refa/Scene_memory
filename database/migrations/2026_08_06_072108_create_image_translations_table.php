<?php

use App\Models\EventsImges;
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
        Schema::create('image_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(EventsImges::class,'image_id')->constrained()->cascadeOnDelete();
            $table->string('locale');
            $table->text('description');
            $table->timestamps();
            $table->unique(['image_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_translations');
    }
};
