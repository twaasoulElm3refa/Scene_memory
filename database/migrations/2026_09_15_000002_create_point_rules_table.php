<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_rules', function (Blueprint $table) {
            $table->id();
            $table->string('action')->unique();
            $table->integer('points');
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('point_rules')->insert([
            ['action' => 'event.created', 'points' => 10, 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['action' => 'like.created', 'points' => 2, 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['action' => 'comment.created', 'points' => 3, 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['action' => 'comment.reply', 'points' => 2, 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['action' => 'comment.image', 'points' => 3, 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['action' => 'comment.interaction', 'points' => 1, 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['action' => 'wishlist.created', 'points' => 2, 'status' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('point_rules');
    }
};
