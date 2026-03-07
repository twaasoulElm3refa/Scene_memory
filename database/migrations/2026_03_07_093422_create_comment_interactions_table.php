<?php

use App\Models\comments;
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
        Schema::create('comment_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(comments::class,'comment_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type',['support','Exhibitions','neutral'])->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['comment_id','user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_interactions');
    }
};
