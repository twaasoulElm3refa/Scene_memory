<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $eventsImagesTable = Schema::hasTable('events_images') ? 'events_images' : 'events_imges';

        Schema::create('user_credit_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('licence_id')->nullable()->constrained('licence_types')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->foreignId('media_tier_id')->nullable()->constrained('media_tiers')->nullOnDelete();
            $table->integer('tier_rank');
            $table->string('tier_name');
            $table->unsignedInteger('photo_count')->default(0);
            $table->unsignedInteger('video_count')->default(0);
            $table->unsignedInteger('remaining_photos')->default(0);
            $table->unsignedInteger('remaining_videos')->default(0);
            $table->decimal('max_eligible_photo_price', 10, 2);
            $table->decimal('max_eligible_video_price', 10, 2);
            $table->decimal('payout_cap_photo', 10, 2);
            $table->decimal('payout_cap_video', 10, 2);
            $table->string('payment_method')->default('wallet');
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tier_rank']);
            $table->index(['user_id', 'remaining_photos']);
            $table->index(['user_id', 'remaining_videos']);
        });

        Schema::create('credit_transactions', function (Blueprint $table) use ($eventsImagesTable) {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grant_id')->constrained('user_credit_grants')->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained($eventsImagesTable)->nullOnDelete();
            $table->enum('media_type', ['photo', 'video']);
            $table->enum('action', ['grant', 'redemption', 'reversal']);
            $table->integer('quantity'); // +N for grant, -1 for redemption
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['grant_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('user_credit_grants');
    }
};
