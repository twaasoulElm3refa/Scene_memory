<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('type')->default('single')->after('image_id')->comment('single | collection');
            $table->foreignId('event_id')->nullable()->after('type')->constrained('events')->cascadeOnDelete()->comment('Used for collection purchases');
            $table->decimal('discount', 10, 2)->default(0)->after('price')->comment('Discount amount applied');
            $table->json('collection_images')->nullable()->after('discount')->comment('Images in collection (for collection type)');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['type', 'event_id', 'discount', 'collection_images']);
        });
    }
};
