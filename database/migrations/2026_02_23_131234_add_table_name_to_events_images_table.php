<?php

use App\Models\MediaRequest;
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
        Schema::table('events_imges', function (Blueprint $table) {
            $table->foreignIdFor(MediaRequest::class,'media_request_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_images', function (Blueprint $table) {
            //
        });
    }
};
