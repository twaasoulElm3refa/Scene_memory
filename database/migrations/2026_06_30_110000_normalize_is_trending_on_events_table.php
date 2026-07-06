<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'is_trending')) {
            Schema::table('events', function (Blueprint $table) {
                $table->boolean('is_trending')->default(false);
            });

            return;
        }

        DB::table('events')
            ->whereNull('is_trending')
            ->update(['is_trending' => false]);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE events MODIFY is_trending TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'is_trending') && DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE events MODIFY is_trending TINYINT(1) NULL DEFAULT 0');
        }
    }
};
