<?php

namespace Tests\Feature\Api\Events;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventSearchFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for event search filter tests.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
        $this->withoutMiddleware();
    }

    public function test_country_and_tag_filters_are_applied_together(): void
    {
        $countryOneCityId = $this->insertCity(1, 'Cairo');
        $countryTwoCityId = $this->insertCity(2, 'Paris');

        $matchingEventId = $this->insertEvent($countryOneCityId, 'matching-event', '2026-01-01');
        $outsideCountryEventId = $this->insertEvent($countryTwoCityId, 'outside-country', '2026-01-02');
        $sameCountryDifferentTagId = $this->insertEvent($countryOneCityId, 'different-tag', '2026-01-03');

        DB::table('event__tags')->insert([
            [
                'event_id' => $matchingEventId,
                'tag_id' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $outsideCountryEventId,
                'tag_id' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $sameCountryDifferentTagId,
                'tag_id' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/events/all/all?country_id=1&tags_id[]=7');

        $response->assertOk();

        $this->assertSame([$matchingEventId], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_country_filter_returns_only_events_inside_that_country(): void
    {
        $countryOneCityId = $this->insertCity(1, 'Cairo');
        $countryTwoCityId = $this->insertCity(2, 'Paris');

        $firstEventId = $this->insertEvent($countryOneCityId, 'first-country-event', '2026-01-01');
        $secondEventId = $this->insertEvent($countryOneCityId, 'second-country-event', '2026-01-02');
        $this->insertEvent($countryTwoCityId, 'outside-country', '2026-01-03');

        $response = $this->getJson('/api/v1/events/all/all?country_id=1');

        $response->assertOk();

        $this->assertSame([$secondEventId, $firstEventId], collect($response->json('data.data'))->pluck('id')->all());
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('images_tags');
        Schema::dropIfExists('event__tags');
        Schema::dropIfExists('events_images');
        Schema::dropIfExists('event_translations');
        Schema::dropIfExists('sub_categorey_translations');
        Schema::dropIfExists('sub_categoreys');
        Schema::dropIfExists('city_translations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('cities');

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('city_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->string('locale')->index();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_categoreys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_categorey_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('locale')->index();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('sub_categorey_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->string('langitude')->nullable();
            $table->string('lattitude')->nullable();
            $table->string('slug')->nullable();
            $table->string('photography_type')->nullable();
            $table->string('is_active')->default('1');
            $table->timestamps();
        });

        Schema::create('event_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('locale')->index();
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('events_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('full_url')->nullable();
            $table->timestamps();
        });

        Schema::create('event__tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('tag_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('images_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('events_imges_id');
            $table->unsignedBigInteger('tags_id');
            $table->timestamps();
        });
    }

    private function insertCity(int $countryId, string $name): int
    {
        $cityId = DB::table('cities')->insertGetId([
            'country_id' => $countryId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('city_translations')->insert([
            'city_id' => $cityId,
            'locale' => app()->getLocale(),
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cityId;
    }

    private function insertEvent(int $cityId, string $slug, string $startDate): int
    {
        $eventId = DB::table('events')->insertGetId([
            'city_id' => $cityId,
            'sub_categorey_id' => 1,
            'title' => $slug,
            'description' => $slug,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'slug' => $slug,
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_translations')->insert([
            'event_id' => $eventId,
            'locale' => app()->getLocale(),
            'title' => $slug,
            'description' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $eventId;
    }
}
