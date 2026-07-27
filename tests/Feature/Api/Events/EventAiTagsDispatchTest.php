<?php

namespace Tests\Feature\Api\Events;

use App\Jobs\GenerateEventAiTagsJob;
use App\Jobs\ProcessEventImageJob;
use App\Jobs\TranslateEventJob;
use App\Models\User;
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
        $this->assertEndpointDispatchesBatch('/api/v1/events/create/user', false);
    }

    public function test_historic_dispatches_an_image_batch_and_ai_once_from_its_finally_callback(): void
    {
        $this->assertEndpointDispatchesBatch('/api/v1/events/historic/user', true);
    }

    private function assertEndpointDispatchesBatch(string $uri, bool $historical): void
    {
        Bus::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

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
        $testTransactionLevel = DB::transactionLevel();

        $response = $this->post($uri, [
            'title' => 'Queued event',
            'description' => 'The response must not call OpenRouter.',
            'city_id' => $cityId,
            'sub_categorey_id' => $subCategoryId,
            'photography_type' => 'normal',
            'urls' => [UploadedFile::fake()->createWithContent(
                'event.png',
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
                )
            )],
            'start_date' => now()->toDateString(),
            'photo_descriptions' => ['Manual photo description'],
            'photo_tags_json' => [json_encode(['new_tags' => ['Manual photo tag']])],
            'new_tags' => ['Manual event tag'],
        ]);

        $response->assertOk();
        $eventId = (int) $response->json('data.id');
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'is_historical' => $historical ? 1 : 0,
        ]);

        $finallyCallback = null;

        Bus::assertBatched(function (PendingBatch $batch) use (
            $eventId,
            $testTransactionLevel,
            &$finallyCallback
        ) {
            $this->assertSame("event-images:{$eventId}", $batch->name);
            $this->assertCount(1, $batch->jobs);
            $this->assertInstanceOf(ProcessEventImageJob::class, $batch->jobs->first());
            $this->assertSame($testTransactionLevel, DB::transactionLevel());
            $finallyCallback = $batch->finallyCallbacks()[0] ?? null;

            return true;
        });

        Bus::assertNotDispatched(GenerateEventAiTagsJob::class);
        Bus::assertDispatched(TranslateEventJob::class);
        $this->assertIsCallable($finallyCallback);

        $finallyCallback(Mockery::mock(Batch::class));

        Bus::assertDispatchedTimes(GenerateEventAiTagsJob::class, 1);
        Bus::assertDispatched(
            GenerateEventAiTagsJob::class,
            fn (GenerateEventAiTagsJob $job) => $job->eventId === $eventId
        );
    }
}
