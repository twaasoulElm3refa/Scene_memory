<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->enum('mode', ['ai', 'user'])
                ->default('ai')
                ->after('name');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_slug_unique');
            $table->unique(['slug', 'mode'], 'tags_slug_mode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_slug_mode_unique');
            $table->dropColumn('mode');
            $table->unique('slug', 'tags_slug_unique');
        });
    }
};
