<?php

use App\Models\subCategorey;
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
        Schema::create('sub_categorey_translations', function (Blueprint $table) {
            $table->id();
             $table->foreignIdFor(subCategorey::class,'category_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['category_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_categorey_translations');
    }
};
