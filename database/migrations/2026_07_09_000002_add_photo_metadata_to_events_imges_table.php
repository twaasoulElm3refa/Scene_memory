<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_imges', function (Blueprint $table) {
            if (! Schema::hasColumn('events_imges', 'description')) {
                $table->text('description')->nullable()->after('price');
            }

            if (! Schema::hasColumn('events_imges', 'tags_json')) {
                $table->json('tags_json')->nullable()->after('description');
            }

            if (! Schema::hasColumn('events_imges', 'quality_score')) {
                $table->decimal('quality_score', 6, 2)->nullable()->after('tags_json');
            }

            if (! Schema::hasColumn('events_imges', 'sharpness_score')) {
                $table->decimal('sharpness_score', 6, 2)->nullable()->after('quality_score');
            }

            if (! Schema::hasColumn('events_imges', 'blur_score')) {
                $table->decimal('blur_score', 6, 2)->nullable()->after('sharpness_score');
            }

            if (! Schema::hasColumn('events_imges', 'megapixels')) {
                $table->decimal('megapixels', 8, 2)->nullable()->after('blur_score');
            }

            if (! Schema::hasColumn('events_imges', 'file_size_mb')) {
                $table->decimal('file_size_mb', 8, 2)->nullable()->after('megapixels');
            }

            if (! Schema::hasColumn('events_imges', 'validation_status')) {
                $table->string('validation_status')->nullable()->after('file_size_mb');
            }

            if (! Schema::hasColumn('events_imges', 'validation_message')) {
                $table->text('validation_message')->nullable()->after('validation_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_imges', function (Blueprint $table) {
            $columns = [
                'description',
                'tags_json',
                'quality_score',
                'sharpness_score',
                'blur_score',
                'megapixels',
                'file_size_mb',
                'validation_status',
                'validation_message',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('events_imges', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
