<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'status')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('status', 16)->default('open')->index();
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->unsignedBigInteger('user_read_message_id')->nullable();
                $table->string('submission_key', 64)->nullable()->unique();
                $table->text('guest_access_token')->nullable();
                $table->timestamp('guest_access_expires_at')->nullable();
            });
        }

        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
                $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sender_type', 16);
                $table->text('message');
                $table->string('idempotency_key', 64)->nullable();
                $table->string('legacy_kind', 16)->nullable();
                $table->unsignedBigInteger('legacy_id')->nullable();
                $table->timestamps();
                $table->unique(['contact_id', 'idempotency_key']);
                $table->unique(['legacy_kind', 'legacy_id']);
                $table->index(['contact_id', 'created_at', 'id']);
            });
        }

        if (! Schema::hasTable('contact_admin_reads')) {
            Schema::create('contact_admin_reads', function (Blueprint $table) {
                $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
                $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('last_read_message_id')->default(0);
                $table->primary(['contact_id', 'admin_id']);
            });
        }

        DB::table('contacts')->orderBy('id')->chunkById(200, function ($contacts) {
            foreach ($contacts as $contact) {
                if ($contact->message !== null) {
                    DB::table('contact_messages')->insertOrIgnore([
                        'contact_id' => $contact->id,
                        'legacy_kind' => 'contact',
                        'legacy_id' => $contact->id,
                        'sender_id' => $contact->user_id,
                        'sender_type' => $contact->user_id ? 'user' : 'guest',
                        'message' => $contact->message,
                        'created_at' => $contact->created_at,
                        'updated_at' => $contact->created_at,
                    ]);
                }
                DB::table('contact_responds')->where('contact_id', $contact->id)
                    ->orderBy('created_at')->orderBy('id')->get()->each(function ($reply) use ($contact) {
                        DB::table('contact_messages')->insertOrIgnore([
                            'contact_id' => $contact->id,
                            'legacy_kind' => 'response',
                            'legacy_id' => $reply->id,
                            'sender_id' => null,
                            'sender_type' => 'admin',
                            'message' => $reply->message ?? '',
                            'created_at' => $reply->created_at,
                            'updated_at' => $reply->created_at,
                        ]);
                    });
                $lastReply = DB::table('contact_responds')->where('contact_id', $contact->id)->max('created_at');
                DB::table('contacts')->where('id', $contact->id)->whereNull('last_message_at')->update([
                    'status' => $lastReply ? 'pending' : 'open',
                    'last_message_at' => $lastReply ?: $contact->created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Keep conversation history on rollback; an explicit archival plan is required.
        throw new RuntimeException('Support conversation migration cannot be rolled back without losing messages.');
    }
};
