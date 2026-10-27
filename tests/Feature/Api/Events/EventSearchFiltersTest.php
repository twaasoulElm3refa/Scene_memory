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

    public function test_legacy_event_search_applies_both_date_bounds(): void
    {
        $cityId = $this->insertCity(1, 'Cairo');
        $this->insertEvent($cityId, 'before-range', '2026-07-31');
        $insideEventId = $this->insertEvent($cityId, 'inside-range', '2026-08-15');
        $this->insertEvent($cityId, 'after-range', '2026-09-01');

        $response = $this->getJson('/api/v1/events/all/all?from=2026-08-01&to=2026-08-31');

        $response->assertOk();
        $this->assertSame([$insideEventId], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_discovery_all_is_mixed_seeded_and_pagination_safe(): void
    {
        $cityId = $this->insertCity(1, 'Cairo');

        foreach (range(1, 3) as $index) {
            $eventId = $this->insertEvent($cityId, "event-{$index}", "2026-01-0{$index}");
            $this->insertMedia($eventId, 'image', "images/{$index}.jpg");
            $this->insertMedia($eventId, 'video', "videos/{$index}.mp4");
        }

        $firstPage = $this->getJson('/api/v1/events/discovery/search?type=all&seed=123&per_page=4&page=1');
        $secondPage = $this->getJson('/api/v1/events/discovery/search?type=all&seed=123&per_page=4&page=2');
        $repeatedFirstPage = $this->getJson('/api/v1/events/discovery/search?type=all&seed=123&per_page=4&page=1');

        $firstPage->assertOk()
            ->assertJsonPath('data.total', 9)
            ->assertJsonPath('data.seed', 123)
            ->assertJsonPath('data.type', 'all');
        $secondPage->assertOk();

        $firstItems = collect($firstPage->json('data.data'));
        $secondItems = collect($secondPage->json('data.data'));
        $identity = fn ($item) => $item['result_type'].':'.$item['id'];

        $this->assertSame(
            ['event', 'image', 'video', 'event'],
            $firstItems->pluck('result_type')->all()
        );
        $this->assertSame(
            $firstItems->map($identity)->all(),
            collect($repeatedFirstPage->json('data.data'))->map($identity)->all()
        );
        $this->assertCount(
            8,
            $firstItems->concat($secondItems)->map($identity)->unique()
        );
    }

    public function test_discovery_tabs_have_independent_totals(): void
    {
        $cityId = $this->insertCity(1, 'Cairo');
        $eventId = $this->insertEvent($cityId, 'documented-event', '2026-01-01');
        $this->insertMedia($eventId, 'image', 'images/photo.jpg');
        $this->insertMedia($eventId, 'image', 'images/photo-2.jpg');
        $this->insertMedia($eventId, 'video', 'videos/clip.mp4');

        $this->getJson('/api/v1/events/discovery/search?type=event&seed=10')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.result_type', 'event');

        $this->getJson('/api/v1/events/discovery/search?type=image&seed=10')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.result_type', 'image')
            ->assertJsonPath('data.data.0.media_type', 'image')
            ->assertJsonPath('data.data.0.price', 12.5);

        $this->getJson('/api/v1/events/discovery/search?type=video&seed=10')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.result_type', 'video')
            ->assertJsonPath('data.data.0.event_id', $eventId)
            ->assertJsonPath('data.data.0.preview_url', fn ($url) => str_contains($url, '/api/v1/media/'))
            ->assertJsonPath('data.data.0.video_url', fn ($url) => str_contains($url, '/api/v1/media/'));
    }

    public function test_discovery_media_matches_its_own_or_parent_event_tags(): void
    {
        $cityId = $this->insertCity(1, 'Cairo');
        $parentTaggedEvent = $this->insertEvent($cityId, 'parent-tagged', '2026-01-01');
        $mediaTaggedEvent = $this->insertEvent($cityId, 'media-tagged', '2026-01-02');
        $unmatchedEvent = $this->insertEvent($cityId, 'unmatched', '2026-01-03');

        DB::table('event__tags')->insert([
            'event_id' => $parentTaggedEvent,
            'tag_id' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentMatchedMedia = $this->insertMedia($parentTaggedEvent, 'image', 'images/parent.jpg');
        $ownMatchedMedia = $this->insertMedia($mediaTaggedEvent, 'image', 'images/own.jpg');
        $this->insertMedia($unmatchedEvent, 'image', 'images/unmatched.jpg');

        DB::table('images_tags')->insert([
            'events_imges_id' => $ownMatchedMedia,
            'tags_id' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/events/discovery/search?type=image&tags=7&seed=12');

        $response->assertOk()->assertJsonPath('data.total', 2);
        $this->assertEqualsCanonicalizing(
            [$parentMatchedMedia, $ownMatchedMedia],
            collect($response->json('data.data'))->pluck('id')->all()
        );
    }

    public function test_discovery_date_range_applies_to_events_and_media(): void
    {
        $cityId = $this->insertCity(1, 'Cairo');
        $insideEvent = $this->insertEvent($cityId, 'inside-range', '2026-08-15');
        $outsideEvent = $this->insertEvent($cityId, 'outside-range', '2026-09-01');
        $insideMedia = $this->insertMedia($insideEvent, 'video', 'videos/inside.mp4');
        $this->insertMedia($outsideEvent, 'video', 'videos/outside.mp4');

        $response = $this->getJson(
            '/api/v1/events/discovery/search?type=video&from_date=2026-08-01&to_date=2026-08-31&seed=20'
        );

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $insideMedia)
            ->assertJsonPath('data.data.0.event_id', $insideEvent);
    }

    public function test_discovery_rejects_invalid_type_and_reversed_dates(): void
    {
        $this->getJson('/api/v1/events/discovery/search?type=audio')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->getJson('/api/v1/events/discovery/search?from=2026-09-01&to=2026-08-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_empty_discovery_search_returns_only_public_content_in_every_locale(): void
    {
        $this->withMiddleware();
        $city = $this->insertCity(1, 'Cairo');
        $approved = $this->insertEvent($city, 'original-title', '2026-01-01');
        $legacy = $this->insertEvent($city, 'fallback-title', '2026-01-02');
        $inactiveMedia = $this->insertMedia($approved, 'image', 'images/inactive.jpg');
        DB::table('events_images')->where('id', $inactiveMedia)->update(['is_active' => '0']);
        $rejectedMedia = $this->insertMedia($approved, 'video', 'videos/rejected.mp4');
        DB::table('media_requests')->insert(['image_id' => $rejectedMedia, 'status' => 'rejected']);
        $pendingMedia = $this->insertMedia($approved, 'image', 'images/pending.jpg');
        DB::table('media_requests')->insert(['image_id' => $pendingMedia, 'status' => 'pending']);
        $image = $this->insertMedia($approved, 'image', 'images/public.jpg');
        DB::table('media_requests')->insert(['image_id' => $image, 'status' => 'approved']);
        $video = $this->insertMedia($approved, 'video', 'videos/public.mp4');
        DB::table('events_images')->where('id', $image)->update(['description' => 'original media description']);

        DB::table('event_request_creates')->insert(['event_id' => $approved, 'status' => 'approved']);
        $inactive = $this->insertEvent($city, 'inactive', '2026-01-03');
        DB::table('events')->where('id', $inactive)->update(['is_active' => '0']);
        $rejected = $this->insertEvent($city, 'rejected', '2026-01-04');
        DB::table('event_request_creates')->insert(['event_id' => $rejected, 'status' => 'rejected']);
        $pending = $this->insertEvent($city, 'pending', '2026-01-05');
        DB::table('event_request_creates')->insert(['event_id' => $pending, 'status' => 'pending']);
        $deleted = $this->insertEvent($city, 'deleted', '2026-01-06');
        DB::table('events')->where('id', $deleted)->update(['deleted_at' => now()]);

        DB::table('event_translations')->insert([
            'event_id' => $approved, 'locale' => 'ar', 'title' => 'عنوان عربي',
            'description' => 'وصف عربي', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('image_translations')->insert([
            'image_id' => $image, 'locale' => 'ar', 'description' => 'صورة عربية',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $expected = ['event:'.$approved, 'event:'.$legacy, 'image:'.$image, 'video:'.$video];
        $expectedPages = null;
        foreach (['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur', 'tr'] as $locale) {
            $pages = collect();
            $pageIdentities = [];
            foreach ([1, 2] as $page) {
                $response = $this->getJson("/api/v1/events/discovery/search?seed=42&per_page=2&page={$page}", [
                    'Accept-Language' => $locale,
                ])->assertOk()->assertJsonPath('data.total', 4);
                $pages = $pages->concat($response->json('data.data'));
                $pageIdentities[] = collect($response->json('data.data'))
                    ->map(fn ($item) => $item['result_type'].':'.$item['id'])->all();
            }
            $this->assertEqualsCanonicalizing($expected, $pages->map(fn ($item) => $item['result_type'].':'.$item['id'])->all());
            if ($expectedPages === null) {
                $expectedPages = $pageIdentities;
            } else {
                $this->assertSame($expectedPages, $pageIdentities);
            }
        }

        $arabic = collect($this->getJson('/api/v1/events/discovery/search?seed=42', ['Accept-Language' => 'ar'])->json('data.data'));
        $arabicEvent = $arabic->first(fn ($item) => $item['result_type'] === 'event' && $item['id'] === $approved);
        $arabicImage = $arabic->first(fn ($item) => $item['result_type'] === 'image' && $item['id'] === $image);
        $this->assertSame('عنوان عربي', $arabicEvent['title']);
        $this->assertSame('وصف عربي', $arabicEvent['description']);
        $this->assertSame('fallback-title', $arabic->first(fn ($item) => $item['result_type'] === 'event' && $item['id'] === $legacy)['title']);
        $this->assertSame('صورة عربية', $arabicImage['description']);
        $this->assertSame($image, $arabicEvent['first_image']['id']);
        $english = collect($this->getJson('/api/v1/events/discovery/search?seed=42', ['Accept-Language' => 'en'])->json('data.data'));
        $this->assertSame('original-title', $english->first(fn ($item) => $item['result_type'] === 'event' && $item['id'] === $approved)['title']);
    }

    public function test_discovery_keyword_search_uses_original_and_all_translations_in_both_locales(): void
    {
        $city = $this->insertCity(1, 'Cairo');
        $original = $this->insertEvent($city, 'museum-original', '2026-01-01');
        $translated = $this->insertEvent($city, 'other-original', '2026-01-02');
        $mediaEvent = $this->insertEvent($city, 'media-parent', '2026-01-03');
        $media = $this->insertMedia($mediaEvent, 'image', 'images/museum.jpg');
        DB::table('event_translations')->insert([
            'event_id' => $translated, 'locale' => 'fr', 'title' => 'museum traduit',
            'description' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('image_translations')->insert([
            'image_id' => $media, 'locale' => 'ar', 'description' => 'museum translated media',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);
            $response = $this->getJson('/api/v1/events/discovery/search?seed=7&q=museum', [
                'Accept-Language' => $locale,
            ])->assertOk()->assertJsonPath('data.total', 3);
            $this->assertEqualsCanonicalizing(
                ['event:'.$original, 'event:'.$translated, 'image:'.$media],
                collect($response->json('data.data'))->map(fn ($item) => $item['result_type'].':'.$item['id'])->all()
            );
        }
    }

    public function test_discovery_individual_and_combined_filters_do_not_change_with_locale(): void
    {
        DB::table('sub_categoreys')->insert(['id' => 1, 'category_id' => 1, 'name' => 'History']);
        $cairo = $this->insertCity(1, 'Cairo');
        $paris = $this->insertCity(2, 'Paris');
        $matching = $this->insertEvent($cairo, 'matching', '2026-08-15');
        $other = $this->insertEvent($paris, 'other', '2026-09-15');
        $image = $this->insertMedia($matching, 'image', 'images/matching.jpg');
        $video = $this->insertMedia($other, 'video', 'videos/other.mp4');
        DB::table('event__tags')->insert(['event_id' => $matching, 'tag_id' => 7]);

        $cases = [
            ['country_id=1', ['event:'.$matching, 'image:'.$image]],
            ["city_id={$cairo}", ['event:'.$matching, 'image:'.$image]],
            ['category_id=1', ['event:'.$matching, 'event:'.$other, 'image:'.$image, 'video:'.$video]],
            ['sub_category_id=1', ['event:'.$matching, 'event:'.$other, 'image:'.$image, 'video:'.$video]],
            ['tags=7', ['event:'.$matching, 'image:'.$image]],
            ['from=2026-08-01&to=2026-08-31', ['event:'.$matching, 'image:'.$image]],
            ["country_id=1&city_id={$cairo}&category_id=1&sub_category_id=1&tags=7&from=2026-08-01&to=2026-08-31&type=image", ['image:'.$image]],
        ];

        foreach ($cases as [$filter, $expected]) {
            foreach (['ar', 'en'] as $locale) {
                app()->setLocale($locale);
                $response = $this->getJson("/api/v1/events/discovery/search?seed=19&{$filter}", [
                    'Accept-Language' => $locale,
                ])->assertOk()->assertJsonPath('data.total', count($expected));
                $this->assertEqualsCanonicalizing(
                    $expected,
                    collect($response->json('data.data'))->map(fn ($item) => $item['result_type'].':'.$item['id'])->all()
                );
            }
        }
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('images_tags');
        Schema::dropIfExists('event__tags');
        Schema::dropIfExists('events_images');
        Schema::dropIfExists('media_requests');
        Schema::dropIfExists('event_request_creates');
        Schema::dropIfExists('image_translations');
        Schema::dropIfExists('event_translations');
        Schema::dropIfExists('sub_categorey_translations');
        Schema::dropIfExists('sub_categoreys');
        Schema::dropIfExists('categorey_translations');
        Schema::dropIfExists('categories');
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

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('categorey_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('locale');
            $table->string('name')->nullable();
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
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('event_request_creates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('status');
        });

        Schema::create('media_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_id');
            $table->string('status');
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
            $table->string('preview_url')->nullable();
            $table->string('full_url')->nullable();
            $table->string('type')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('is_active')->default('1');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('image_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_id');
            $table->string('locale')->index();
            $table->text('description')->nullable();
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

    private function insertMedia(int $eventId, string $type, string $url): int
    {
        return DB::table('events_images')->insertGetId([
            'event_id' => $eventId,
            'preview_url' => $type === 'video' ? "{$url}.jpg" : $url,
            'full_url' => $url,
            'type' => $type,
            'price' => 12.50,
            'is_active' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
