<?php

use App\Models\Categories;
use App\Models\Cities;
use App\Models\subCategorey;
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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class,'user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Cities::class,'city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(subCategorey::class,'sub_categorey_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->string('time')->nullable();
            $table->string('image')->nullable();
            $table->string('langitude')->nullable();
            $table->string('lattitude')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
