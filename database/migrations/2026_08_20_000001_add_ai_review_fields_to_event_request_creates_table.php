<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_request_creates', function (Blueprint $table) {
            $table->string('ai_decision')->nullable()->index()->after('ai_flagged');
            $table->decimal('ai_confidence', 5, 4)->nullable()->after('ai_decision');
            $table->text('ai_reason')->nullable()->after('ai_confidence');
            $table->json('ai_raw_response')->nullable()->after('ai_reason');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_raw_response');
            $table->string('ai_review_status')->nullable()->index()->after('ai_reviewed_at');
            $table->unsignedSmallInteger('ai_attempts')->default(0)->after('ai_review_status');
            $table->string('ai_workflow_execution_id')->nullable()->after('ai_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('event_request_creates', function (Blueprint $table) {
            $table->dropIndex(['ai_decision']);
            $table->dropIndex(['ai_review_status']);
            $table->dropColumn([
                'ai_decision',
                'ai_confidence',
                'ai_reason',
                'ai_raw_response',
                'ai_reviewed_at',
                'ai_review_status',
                'ai_attempts',
                'ai_workflow_execution_id',
            ]);
        });
    }
};
