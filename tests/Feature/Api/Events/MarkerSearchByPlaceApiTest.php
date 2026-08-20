<?php

namespace Tests\Feature\Api\Events;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarkerSearchByPlaceApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $testConnection = env('MARKER_TEST_DB_CONNECTION');

        if ($testConnection === 'mysql') {
            config([
                'database.default' => 'marker_test',
                'database.connections.marker_test' => [
                    'driver' => 'mysql',
                    'host' => env('MARKER_TEST_DB_HOST', 'mysql'),
                    'port' => env('MARKER_TEST_DB_PORT', '3306'),
                    'database' => env('MARKER_TEST_DB_DATABASE', 'scene_memory_marker_test'),
                    'username' => env('MARKER_TEST_DB_USERNAME', 'user'),
                    'password' => env('MARKER_TEST_DB_PASSWORD', 'password'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                ],
            ]);
        } elseif (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped(
                'pdo_sqlite or a MARKER_TEST_DB_CONNECTION=mysql database is required.'
            );
        } else {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
            ]);
        }

        config(['cache.default' => 'array']);

        DB::purge();
        DB::reconnect();

        Schema::dropIfExists('events_images');
        Schema::dropIfExists('event_translations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('city_nominations');

        Schema::create('city_nominations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('osm_id')->nullable();
            $table->string('osm_type')->nullable();
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->decimal('bbox_min_lat', 10, 7)->nullable();
            $table->decimal('bbox_max_lat', 10, 7)->nullable();
            $table->decimal('bbox_min_lng', 10, 7)->nullable();
            $table->decimal('bbox_max_lng', 10, 7)->nullable();
            $table->longText('polygon_geojson')->nullable();
            $table->timestamps();
            $table->unique(['osm_id', 'osm_type']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('sub_categorey_id')->nullable();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->string('image')->nullable();
            $table->string('start_date')->nullable();
            $table->string('langitude')->nullable();
            $table->string('lattitude')->nullable();
            $table->string('is_active')->default('1');
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

        Schema::create('events_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('full_url')->nullable();
            $table->timestamps();
        });

        $this->withoutMiddleware();
    }

    public function test_it_uses_a_cached_nomination_bbox_without_calling_nominatim(): void
    {
        Http::fake();

        DB::table('city_nominations')->insert([
            'osm_id' => '7444',
            'osm_type' => 'R',
            'center_lat' => 48.8566,
            'center_lng' => 2.3522,
            'bbox_min_lat' => 48.80,
            'bbox_max_lat' => 48.90,
            'bbox_min_lng' => 2.20,
            'bbox_max_lng' => 2.45,
            'polygon_geojson' => '{"type":"Polygon","coordinates":[]}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $insideId = $this->insertEvent('inside-paris', 48.8566, 2.3522);
        $this->insertEvent('outside-paris', 51.5074, -0.1278);

        $response = $this->postJson('/api/v1/events/marker/search-by-place', [
            'lat' => 48.8566,
            'lng' => 2.3522,
            'city' => 'Paris',
            'state' => 'Ile-de-France',
            'country_code' => 'fr',
            'osm_id' => 7444,
            'osm_type' => 'relation',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.method', 'bbox')
            ->assertJsonPath('meta.osm_type', 'R')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $insideId);

        Http::assertNothingSent();
    }

    public function test_it_hydrates_a_new_nomination_and_searches_its_bbox(): void
    {
        Http::fake([
            '*/lookup*' => Http::response([[
                'osm_id' => 65606,
                'osm_type' => 'relation',
                'lat' => '51.5074456',
                'lon' => '-0.1277653',
                'boundingbox' => ['51.2867601', '51.6918741', '-0.5103751', '0.3340155'],
                'geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [],
                ],
            ]]),
        ]);

        $insideId = $this->insertEvent('inside-london', 51.5074, -0.1278);
        $this->insertEvent('outside-london', 48.8566, 2.3522);

        $response = $this->postJson('/api/v1/events/marker/search-by-place', [
            'lat' => 51.5074,
            'lng' => -0.1278,
            'city' => 'London',
            'state' => 'England',
            'country_code' => 'gb',
            'osm_id' => 65606,
            'osm_type' => 'relation',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.method', 'bbox')
            ->assertJsonPath('data.0.id', $insideId);

        $this->assertDatabaseHas('city_nominations', [
            'osm_id' => '65606',
            'osm_type' => 'R',
            'bbox_min_lat' => 51.2867601,
            'bbox_max_lng' => 0.3340155,
        ]);

        Http::assertSentCount(1);
    }

    public function test_it_uses_radius_when_nominatim_cannot_resolve_the_place(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        $nearbyId = $this->insertEvent('nearby', 38.9072, -77.0369);
        $this->insertEvent('too-far-away', 39.2904, -76.6122);

        $response = $this->postJson('/api/v1/events/marker/search-by-place', [
            'lat' => 38.9072,
            'lng' => -77.0369,
            'city' => 'Washington',
            'country_code' => 'us',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.source', 'fallback')
            ->assertJsonPath('meta.method', 'radius')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nearbyId);
    }

    public function test_it_searches_nominatim_when_the_osm_identity_is_missing(): void
    {
        Http::fake([
            '*/search*' => Http::response([[
                'osm_id' => 5396194,
                'osm_type' => 'relation',
                'lat' => '38.8950368',
                'lon' => '-77.0365427',
                'boundingbox' => ['38.7916303', '38.9958524', '-77.1197949', '-76.9093660'],
                'geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [],
                ],
            ]]),
        ]);

        $insideId = $this->insertEvent('inside-washington', 38.9072, -77.0369);

        $response = $this->postJson('/api/v1/events/marker/search-by-place', [
            'lat' => 38.9072,
            'lng' => -77.0369,
            'city' => 'Washington',
            'state' => 'District of Columbia',
            'country_code' => 'us',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.osm_id', '5396194')
            ->assertJsonPath('meta.osm_type', 'R')
            ->assertJsonPath('data.0.id', $insideId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && $request['q'] === 'Washington, District of Columbia, us';
        });
    }

    private function insertEvent(string $slug, float $lat, float $lng): int
    {
        return DB::table('events')->insertGetId([
            'title' => $slug,
            'slug' => $slug,
            'lattitude' => (string) $lat,
            'langitude' => (string) $lng,
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
