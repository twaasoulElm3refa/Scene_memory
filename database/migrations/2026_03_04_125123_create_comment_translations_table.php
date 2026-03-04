<?php

use App\Models\comments;
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
        Schema::create('comment_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(comments::class, 'comment_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['locale', 'comment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_translations');
    }
};
