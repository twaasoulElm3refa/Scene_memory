<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('is_active', 'events_is_active_index');
            $table->index('city_id', 'events_city_id_index');
            $table->index('sub_categorey_id', 'events_sub_categorey_id_index');
            $table->index('start_date', 'events_start_date_index');
            $table->index('slug', 'events_slug_index');
            $table->index(
                ['is_active', 'city_id', 'start_date'],
                'events_active_city_start_index'
            );
            $table->index(
                ['is_active', 'city_id', 'sub_categorey_id', 'start_date'],
                'events_active_city_subcategory_start_index'
            );
        });

        Schema::table('event__tags', function (Blueprint $table) {
            $table->index(['tag_id', 'event_id'], 'event_tags_tag_event_index');
            $table->index(['event_id', 'tag_id'], 'event_tags_event_tag_index');
        });

        Schema::table('images_tags', function (Blueprint $table) {
            $table->index(['tags_id', 'events_imges_id'], 'images_tags_tag_image_index');
            $table->index(['events_imges_id', 'tags_id'], 'images_tags_image_tag_index');
        });

        Schema::table('events_imges', function (Blueprint $table) {
            $table->index('event_id', 'events_imges_event_id_index');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->index('country_id', 'cities_country_id_index');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->index('name', 'tags_name_index');
            $table->index('slug', 'tags_slug_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_is_active_index');
            $table->dropIndex('events_city_id_index');
            $table->dropIndex('events_sub_categorey_id_index');
            $table->dropIndex('events_start_date_index');
            $table->dropIndex('events_slug_index');
            $table->dropIndex('events_active_city_start_index');
            $table->dropIndex('events_active_city_subcategory_start_index');
        });

        Schema::table('event__tags', function (Blueprint $table) {
            $table->dropIndex('event_tags_tag_event_index');
            $table->dropIndex('event_tags_event_tag_index');
        });

        Schema::table('images_tags', function (Blueprint $table) {
            $table->dropIndex('images_tags_tag_image_index');
            $table->dropIndex('images_tags_image_tag_index');
        });

        Schema::table('events_imges', function (Blueprint $table) {
            $table->dropIndex('events_imges_event_id_index');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('cities_country_id_index');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex('tags_name_index');
            $table->dropIndex('tags_slug_index');
        });
    }
};
