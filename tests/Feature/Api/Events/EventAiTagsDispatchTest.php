<?php

namespace Tests\Feature\Api\Events;

use App\Jobs\GenerateEventAiTagsJob;
use App\Jobs\ProcessEventImageJob;
use App\Jobs\ReviewEventRequestWithAi;
use App\Jobs\TranslateEventJob;
use App\Models\EventsImges;
use App\Models\Tags;
use App\Models\User;
use App\Services\EventTagCacheService;
use App\Services\ImageAnalysisService;
use App\Services\TagResolverService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class EventAiTagsDispatchTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = sys_get_temp_dir().'/scene-memory-dispatch-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->storageRoot);
        config()->set('filesystems.disks.public.root', $this->storageRoot);
        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('public');
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_create_dispatches_an_image_batch_and_ai_once_from_its_finally_callback(): void
    {
        $this->assertEndpointDispatchesBatch('/api/v1/events/create/user', false, true);
    }

    public function test_historic_public_event_dispatches_the_same_pipeline(): void
    {
        $this->assertEndpointDispatchesBatch('/api/v1/events/historic/user', true, true);
    }

    public function test_historic_personal_event_preserves_is_real_and_dispatches_the_same_pipeline(): void
    {
        $this->assertEndpointDispatchesBatch('/api/v1/events/historic/user', true, false);
    }

    public function test_admin_event_uses_the_same_media_pipeline_and_is_published_immediately(): void
    {
        $this->assertEndpointDispatchesBatch('/api/v1/events/create', false, true, true);
    }

    public function test_admin_can_publish_an_event_as_trending(): void
    {
        $this->assertEndpointDispatchesBatch('/api/v1/events/create', false, true, true, true);
    }

    public function test_public_creation_cannot_inject_the_trending_or_active_state(): void
    {
        $this->assertEndpointDispatchesBatch('/api/v1/events/create/user', false, true, false, true);
    }

    public function test_non_admin_cannot_use_the_admin_creation_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->post('/api/v1/events/create', [])->assertUnauthorized();
    }

    private function assertEndpointDispatchesBatch(
        string $uri,
        bool $historical,
        bool $isReal,
        bool $admin = false,
        bool $requestedTrending = false
    ): void {
        Bus::fake();

        $user = User::factory()->create(['role' => $admin ? 'admin' : 'user']);
        Sanctum::actingAs($user);

        if ($admin) {
            // Prime the endpoint cache so the post-create assertion also
            // proves the creation flow invalidates trending data.
            $this->getJson('/api/v1/events/trending')->assertOk();
        }

        $now = now();
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Test Country',
            'code' => 'TC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Test City',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'News',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subCategoryId = DB::table('sub_categoreys')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Reports',
            'slug' => 'reports',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $existingEventTag = Tags::create([
            'name' => 'Existing event tag',
            'slug' => 'existing-event-tag',
            'mode' => 'user',
        ]);
        $existingPhotoTag = Tags::create([
            'name' => 'Existing photo tag',
            'slug' => 'existing-photo-tag',
            'mode' => 'user',
        ]);
        $testTransactionLevel = DB::transactionLevel();

        $response = $this->post($uri, [
            'title' => 'Queued event',
            'description' => 'The response must not call OpenRouter.',
            'city_id' => $cityId,
            'sub_categorey_id' => $subCategoryId,
            'is_real' => $isReal ? '1' : '0',
            'is_historical' => $historical ? '0' : '1',
            'photography_type' => 'normal',
            'urls' => [UploadedFile::fake()->createWithContent(
                'event.png',
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
                )
            )],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'time' => '09:30',
            'lattitude' => '30.0444',
            'langitude' => '31.2357',
            'is_active' => '1',
            'is_trending' => $requestedTrending ? '1' : '0',
            'status' => 'approved',
            'photo_descriptions' => ['Manual photo description'],
            'photo_tags_json' => [json_encode([
                'tags_id' => [$existingPhotoTag->id],
                'new_tags' => ['Manual photo tag'],
            ])],
            'media_prices' => ['25.50'],
            'tags_id' => [$existingEventTag->id],
            'new_tags' => ['Manual event tag'],
        ]);

        $response->assertOk();
        $eventId = (int) $response->json('data.id');
        $eventRequestId = null;
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'is_historical' => $historical ? 1 : 0,
            'is_real' => $isReal ? 1 : 0,
            'is_active' => $admin ? 1 : 0,
            'is_trending' => $admin && $requestedTrending ? 1 : 0,
            'lattitude' => '30.0444',
            'langitude' => '31.2357',
        ]);
        $this->assertSame("event-queued-event{$eventId}", $response->json('data.slug'));
        $this->assertIsArray($response->json('data.translations'));
        $this->assertIsArray($response->json('data.photos'));
        if ($admin) {
            $this->assertDatabaseMissing('event_request_creates', ['event_id' => $eventId]);

            $trendingEventIds = collect(
                $this->getJson('/api/v1/events/trending')
                    ->assertOk()
                    ->json('data')
            )->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($requestedTrending) {
                $this->assertContains($eventId, $trendingEventIds);
            } else {
                $this->assertNotContains($eventId, $trendingEventIds);
            }
        } else {
            $this->assertDatabaseHas('event_request_creates', [
                'event_id' => $eventId,
                'status' => 'pending',
            ]);
            $eventRequestId = (int) DB::table('event_request_creates')
                ->where('event_id', $eventId)
                ->value('id');
        }
        $this->assertDatabaseHas('event_translations', [
            'event_id' => $eventId,
            'locale' => 'ar',
            'title' => 'Queued event',
        ]);
        $this->assertDatabaseHas('tags', [
            'name' => 'Manual event tag',
            'mode' => 'user',
        ]);
        $this->assertDatabaseHas('tags', [
            'name' => 'Manual photo tag',
            'mode' => 'user',
        ]);
        $this->assertDatabaseMissing('tags', [
            'name' => 'Manual event tag',
            'mode' => 'ai',
        ]);
        $this->assertDatabaseMissing('tags', [
            'name' => 'Manual photo tag',
            'mode' => 'ai',
        ]);
        $manualEventTagId = (int) Tags::query()
            ->where('name', 'Manual event tag')
            ->where('mode', 'user')
            ->value('id');
        $manualPhotoTagId = (int) Tags::query()
            ->where('name', 'Manual photo tag')
            ->where('mode', 'user')
            ->value('id');
        $this->assertDatabaseHas('event__tags', [
            'event_id' => $eventId,
            'tag_id' => $existingEventTag->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('event__tags', [
            'event_id' => $eventId,
            'tag_id' => $manualEventTagId,
            'deleted_at' => null,
        ]);

        $finallyCallback = null;
        $capturedImageJob = null;

        Bus::assertBatched(function (PendingBatch $batch) use (
            $eventId,
            $testTransactionLevel,
            $existingPhotoTag,
            $manualPhotoTagId,
            &$finallyCallback,
            &$capturedImageJob
        ) {
            $this->assertSame("event-media:{$eventId}", $batch->name);
            $this->assertCount(1, $batch->jobs);
            $imageJob = $batch->jobs->first();
            $this->assertInstanceOf(ProcessEventImageJob::class, $imageJob);
            $this->assertSame($eventId, $imageJob->eventId);
            $this->assertSame(25.5, $imageJob->manualPrice);
            $this->assertSame('Manual photo description', $imageJob->metadata['description']);
            $this->assertEqualsCanonicalizing(
                [$existingPhotoTag->id, $manualPhotoTagId],
                $imageJob->metadata['tag_ids']
            );
            $capturedImageJob = $imageJob;
            $this->assertSame($testTransactionLevel, DB::transactionLevel());
            $finallyCallback = $batch->finallyCallbacks()[0] ?? null;

            return true;
        });

        Bus::assertNotDispatched(GenerateEventAiTagsJob::class);
        Bus::assertNotDispatched(ReviewEventRequestWithAi::class);
        Bus::assertDispatched(TranslateEventJob::class);
        $this->assertInstanceOf(ProcessEventImageJob::class, $capturedImageJob);
        if (extension_loaded('gd') || extension_loaded('imagick')) {
            $capturedImageJob->handle(
                app(ImageAnalysisService::class),
                app(TagResolverService::class),
                app(EventTagCacheService::class)
            );
            $storedImage = DB::table((new EventsImges)->getTable())
                ->where('event_id', $eventId)
                ->first();
            $this->assertNotNull($storedImage);
            $this->assertSame('Manual photo description', $storedImage->description);
            $this->assertSame(25.5, (float) $storedImage->price);
            $this->assertTrue(Storage::disk('public')->exists($storedImage->full_url));
            $this->assertTrue(Storage::disk('public')->exists($storedImage->preview_url));
            $this->assertDatabaseHas('images_tags', [
                'events_imges_id' => $storedImage->id,
                'tags_id' => $existingPhotoTag->id,
            ]);
            $this->assertDatabaseHas('images_tags', [
                'events_imges_id' => $storedImage->id,
                'tags_id' => $manualPhotoTagId,
            ]);
        }
        $this->assertIsCallable($finallyCallback);

        $finallyCallback(Mockery::mock(Batch::class));

        Bus::assertDispatchedTimes(GenerateEventAiTagsJob::class, 1);
        Bus::assertDispatched(
            GenerateEventAiTagsJob::class,
            fn (GenerateEventAiTagsJob $job) => $job->eventId === $eventId
        );

        if ($admin) {
            Bus::assertNotDispatched(ReviewEventRequestWithAi::class);
        } else {
            Bus::assertDispatchedTimes(ReviewEventRequestWithAi::class, 1);
            Bus::assertDispatched(
                ReviewEventRequestWithAi::class,
                fn (ReviewEventRequestWithAi $job) => $job->requestId === $eventRequestId
            );
        }
    }
}
