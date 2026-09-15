<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('total_points')->default(0)->after('is_active');
        });

        if (Schema::hasColumn('users', 'points')) {
            DB::table('users')->update([
                'total_points' => DB::raw('COALESCE(points, 0)'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('total_points');
        });
    }
};
