<?php

use App\Models\Events;
use App\Models\Tags;
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
        Schema::create('event__tags', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Events::class,'event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Tags::class,'tag_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event__tags');
    }
};
