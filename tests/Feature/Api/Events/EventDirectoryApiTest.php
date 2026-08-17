<?php

namespace Tests\Feature\Api\Events;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventDirectoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for event directory tests.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);

        DB::purge();
        DB::reconnect();
        $this->createSchema();
    }

    public function test_historical_directory_keeps_its_dataset_and_applies_server_filters(): void
    {
        $cairo = $this->insertCity(1, 'Cairo');
        $paris = $this->insertCity(2, 'Paris');
        $history = $this->insertSubCategory(10, 'History');
        $sports = $this->insertSubCategory(20, 'Sports');

        $older = $this->insertEvent($cairo, $history, 'Ancient Cairo', 'ancient-cairo', '1900-01-01', true, true);
        $newer = $this->insertEvent($cairo, $history, 'Modern Cairo', 'modern-cairo', '1950-01-01', true, false);
        $this->insertEvent($paris, $history, 'Ancient Paris', 'ancient-paris', '1800-01-01', true, true);
        $this->insertEvent($cairo, $sports, 'Ancient Games', 'ancient-games', '1890-01-01', true, true);
        $this->insertEvent($cairo, $history, 'Ancient Normal', 'ancient-normal', '2000-01-01', false, true);

        $response = $this->getJson("/api/v1/events/historical?country_id=1&city_id={$cairo}&category_id=10&sub_category_id={$history}&q=Cairo&from=1895-01-01&to=1960-01-01&sort=oldest");

        $response->assertOk();
        $this->assertSame([$older, $newer], collect($response->json('data.data'))->pluck('id')->all());
        $this->assertTrue(collect($response->json('data.data'))->every(fn ($event) => (bool) $event['is_historical']));
    }

    public function test_normal_directory_preserves_event_type_and_cache_identity_across_filters(): void
    {
        $cairo = $this->insertCity(1, 'Cairo');
        $history = $this->insertSubCategory(10, 'History');
        $sports = $this->insertSubCategory(20, 'Sports');

        $historyReal = $this->insertEvent($cairo, $history, 'Real History', 'real-history', '2020-01-01', false, true);
        $sportsReal = $this->insertEvent($cairo, $sports, 'Real Sports', 'real-sports', '2022-01-01', false, true);
        $this->insertEvent($cairo, $history, 'General History', 'general-history', '2023-01-01', false, false);

        $historyResponse = $this->getJson('/api/v1/events?is_real=1&category_id=10&sort=newest');
        $sportsResponse = $this->getJson('/api/v1/events?is_real=1&category_id=20&sort=newest');

        $historyResponse->assertOk();
        $sportsResponse->assertOk();
        $this->assertSame([$historyReal], collect($historyResponse->json('data.data'))->pluck('id')->all());
        $this->assertSame([$sportsReal], collect($sportsResponse->json('data.data'))->pluck('id')->all());
    }

    public function test_directory_rejects_unsupported_sort_values(): void
    {
        $this->getJson('/api/v1/events?sort=random')->assertUnprocessable();
        $this->getJson('/api/v1/events/historical?sort=random')->assertUnprocessable();
        $this->getJson('/api/v1/events?from=2026-02-01&to=2026-01-01')->assertUnprocessable();
    }

    private function createSchema(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('city_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->string('locale');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_categoreys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_categorey_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('locale');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('sub_categorey_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_real')->default(false);
            $table->boolean('is_historical')->default(false);
            $table->timestamps();
        });

        Schema::create('event_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('locale');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('events_imges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('preview_url')->nullable();
            $table->string('full_url')->nullable();
            $table->timestamps();
        });
    }

    private function insertCity(int $countryId, string $name): int
    {
        $id = DB::table('cities')->insertGetId([
            'country_id' => $countryId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('city_translations')->insert([
            'city_id' => $id,
            'locale' => app()->getLocale(),
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertSubCategory(int $categoryId, string $name): int
    {
        $id = DB::table('sub_categoreys')->insertGetId([
            'category_id' => $categoryId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sub_categorey_translations')->insert([
            'category_id' => $id,
            'locale' => app()->getLocale(),
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertEvent(
        int $cityId,
        int $subCategoryId,
        string $title,
        string $slug,
        string $startDate,
        bool $historical,
        bool $real
    ): int {
        $id = DB::table('events')->insertGetId([
            'city_id' => $cityId,
            'sub_categorey_id' => $subCategoryId,
            'title' => $title,
            'description' => $title,
            'start_date' => $startDate,
            'slug' => $slug,
            'is_active' => true,
            'is_real' => $real,
            'is_historical' => $historical,
            'created_at' => $startDate,
            'updated_at' => $startDate,
        ]);

        DB::table('event_translations')->insert([
            'event_id' => $id,
            'locale' => app()->getLocale(),
            'title' => $title,
            'description' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
