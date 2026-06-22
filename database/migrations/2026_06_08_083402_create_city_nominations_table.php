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
        Schema::create('city_nominations', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Cities::class, 'city_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('osm_id')->nullable()->index();
            $table->string('osm_type')->nullable();

            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();

            $table->decimal('bbox_min_lat', 10, 7)->nullable();
            $table->decimal('bbox_max_lat', 10, 7)->nullable();
            $table->decimal('bbox_min_lng', 10, 7)->nullable();
            $table->decimal('bbox_max_lng', 10, 7)->nullable();

            $table->longText('polygon_geojson')->nullable();

            $table->timestamps();

            $table->unique(['osm_id', 'osm_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_nominations');
    }
};
