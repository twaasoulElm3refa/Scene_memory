<?php

use App\Models\EventsImges;
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
        Schema::create('images_tags', function (Blueprint $table) {
            $table->id();
	    $table->foreignIdFor(EventsImges::class,'events_imges_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignIdFor(Tags::class,'tags_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images_tags');
    }
};
