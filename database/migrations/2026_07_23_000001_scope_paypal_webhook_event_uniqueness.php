<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paypal_webhook_events')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('paypal_webhook_events'))->pluck('name');

        if ($indexes->contains('paypal_webhook_events_event_id_unique')) {
            Schema::table('paypal_webhook_events', function (Blueprint $table) {
                $table->dropUnique('paypal_webhook_events_event_id_unique');
            });
        }

        $indexes = collect(Schema::getIndexes('paypal_webhook_events'))->pluck('name');
        if ($indexes->contains('paypal_webhook_events_event_type_unique')) {
            return;
        }

        DB::table('paypal_webhook_events')
            ->select('event_id', 'webhook_type')
            ->groupBy('event_id', 'webhook_type')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('event_id')
            ->chunk(100, function ($duplicates): void {
                foreach ($duplicates as $duplicate) {
                    $idsToKeep = DB::table('paypal_webhook_events')
                        ->where('event_id', $duplicate->event_id)
                        ->where('webhook_type', $duplicate->webhook_type)
                        ->orderBy('id')
                        ->pluck('id')
                        ->skip(1);

                    if ($idsToKeep->isNotEmpty()) {
                        DB::table('paypal_webhook_events')->whereIn('id', $idsToKeep)->delete();
                    }
                }
            });

        Schema::table('paypal_webhook_events', function (Blueprint $table) {
            $table->unique(['event_id', 'webhook_type'], 'paypal_webhook_events_event_type_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('paypal_webhook_events')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('paypal_webhook_events'))->pluck('name');
        if ($indexes->contains('paypal_webhook_events_event_type_unique')) {
            Schema::table('paypal_webhook_events', function (Blueprint $table) {
                $table->dropUnique('paypal_webhook_events_event_type_unique');
            });
        }

        $duplicates = DB::table('paypal_webhook_events')
            ->select('event_id')
            ->groupBy('event_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $duplicates) {
            Schema::table('paypal_webhook_events', function (Blueprint $table) {
                $table->unique('event_id', 'paypal_webhook_events_event_id_unique');
            });
        }
    }
};
