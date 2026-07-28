<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateEventAiTagsJob;
use App\Models\Event_Tags;
use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Tags;
use App\Services\EventAiTagsPersistenceService;
use App\Services\GenerateImageTagsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GenerateEventAiTagsJobTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = sys_get_temp_dir().'/scene-memory-ai-tags-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->storageRoot);
        config()->set('filesystems.disks.public.root', $this->storageRoot);
        Storage::forgetDisk('public');
        config()->set('services.openrouter.api_key', 'test-key');
        config()->set('services.openrouter.api_url', 'https://openrouter.test/api/v1');
        config()->set('services.openrouter.model', 'test/vision-model');
        config()->set('ai_tags.images_limit', 5);
        config()->set('ai_tags.event_tags_limit', 8);
        config()->set('ai_tags.image_tags_limit', 10);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('public');
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_it_persists_event_and_image_tags_in_their_own_pivots_idempotently(): void
    {
        Log::spy();

        $event = Events::create([
            'title' => 'Historic city',
            'description' => 'A report about the old city',
        ]);

        $firstImage = $this->createStoredImage($event, 'events/full/first.png');
        $secondImage = $this->createStoredImage($event, 'events/full/second.png');

        $manualEventTag = Tags::create(['name' => 'Manual event', 'slug' => 'manual-event']);
        Event_Tags::create(['event_id' => $event->id, 'tag_id' => $manualEventTag->id]);

        $manualImageTag = Tags::create(['name' => 'Manual image', 'slug' => 'manual-image']);
        $firstImage->tags()->attach($manualImageTag->id);

        $softDeletedTag = Tags::create(['name' => 'Restored', 'slug' => 'restored']);
        $softDeletedTag->delete();

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'event_tags' => [' Tourism ', 'Shared', 'tourism', 'Restored'],
                            'images' => [
                                ['image_index' => 2, 'tags' => ['Second image', 'Shared']],
                                ['image_index' => 1, 'tags' => ['First image', 'Shared']],
                                ['image_index' => 999, 'tags' => ['Wrong image']],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->runJob($event->id);
        $this->runJob($event->id);

        $this->assertSame(1, Tags::where('slug', 'tourism')->count());
        $this->assertSame(1, Tags::where('slug', 'shared')->count());
        $this->assertFalse($softDeletedTag->fresh()->trashed());

        $eventTagNames = DB::table('event__tags')
            ->join('tags', 'tags.id', '=', 'event__tags.tag_id')
            ->where('event__tags.event_id', $event->id)
            ->whereNull('event__tags.deleted_at')
            ->pluck('tags.name')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['Manual event', 'Tourism', 'Shared', 'Restored'],
            $eventTagNames
        );
        $this->assertNotContains('First image', $eventTagNames);
        $this->assertNotContains('Second image', $eventTagNames);

        $this->assertEqualsCanonicalizing(
            ['Manual image', 'First image', 'Shared'],
            $firstImage->fresh()->tags()->pluck('tags.name')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['Second image', 'Shared'],
            $secondImage->fresh()->tags()->pluck('tags.name')->all()
        );

        $this->assertSame(
            DB::table('event__tags')->count(),
            DB::table('event__tags')
                ->select('event_id', 'tag_id')
                ->distinct()
                ->count()
        );
        $this->assertSame(
            DB::table('images_tags')->count(),
            DB::table('images_tags')
                ->select('events_imges_id', 'tags_id')
                ->distinct()
                ->count()
        );

        Log::shouldHaveReceived('info')
            ->with(
                'GenerateEventAiTagsJob: AI request started',
                Mockery::on(fn (array $context) => $context === [
                    'event_id' => $event->id,
                    'images_count' => 2,
                    'model' => 'test/vision-model',
                ])
            )
            ->twice();
        Log::shouldHaveReceived('info')
            ->with(
                'GenerateEventAiTagsJob: AI request completed',
                Mockery::on(fn (array $context) => $context === [
                    'event_id' => $event->id,
                    'event_tags_count' => 3,
                    'images_results_count' => 2,
                ])
            )
            ->twice();
    }

    public function test_it_sends_only_the_first_five_existing_images_and_excludes_video(): void
    {
        $event = Events::create([
            'title' => 'Many images',
            'description' => 'Image limit test',
        ]);

        for ($index = 1; $index <= 6; $index++) {
            $this->createStoredImage($event, "events/full/{$index}.png");
        }

        EventsImges::create([
            'event_id' => $event->id,
            'type' => 'video',
            'full_url' => 'videos/report.mp4',
        ]);
        Storage::disk('public')->put('videos/report.mp4', 'video-bytes');

        EventsImges::create([
            'event_id' => $event->id,
            'type' => 'image',
            'full_url' => 'events/full/missing.png',
        ]);

        Http::fake(function ($request) {
            $imageParts = collect($request->data()['messages'][0]['content'])
                ->where('type', 'image_url')
                ->values();

            $this->assertCount(5, $imageParts);
            $imageParts->each(function (array $part): void {
                $this->assertStringStartsWith(
                    'data:image/png;base64,',
                    $part['image_url']['url']
                );
            });

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"event_tags":[],"images":[]}',
                    ],
                ]],
            ]);
        });

        $this->runJob($event->id);

        Http::assertSentCount(1);
    }

    public function test_invalid_provider_json_retries_without_deleting_event_or_images(): void
    {
        Log::spy();

        $event = Events::create([
            'title' => 'Provider failure',
            'description' => 'Invalid response test',
        ]);
        $image = $this->createStoredImage($event, 'events/full/failure.png');

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'not-json'],
                ]],
            ]),
        ]);

        try {
            $this->runJob($event->id);
            $this->fail('The job should throw so the queue can retry it.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invalid JSON response from provider.', $exception->getMessage());
        }

        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseHas('events_imges', ['id' => $image->id]);
        $this->assertDatabaseCount('event__tags', 0);
        $this->assertDatabaseCount('images_tags', 0);

        Log::shouldHaveReceived('error')
            ->with(
                'GenerateEventAiTagsJob: invalid AI JSON response',
                Mockery::on(fn (array $context) => $context['event_id'] === $event->id
                    && $context['model'] === 'test/vision-model'
                    && is_string($context['message'])
                    && $context['message'] !== '')
            )
            ->once();
    }

    public function test_http_failure_logs_only_safe_provider_diagnostics(): void
    {
        Log::spy();

        $event = Events::create([
            'title' => 'Provider HTTP failure',
            'description' => 'Safe logging test',
        ]);
        $this->createStoredImage($event, 'events/full/http-failure.png');

        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => " Rate limit\nexceeded ",
                ],
                'sensitive_response_field' => 'must-not-be-logged',
            ], 429),
        ]);

        try {
            $this->runJob($event->id);
            $this->fail('The job should throw so the queue can retry it.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Image tags provider request failed.', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')
            ->with(
                'GenerateEventAiTagsJob: OpenRouter request failed',
                Mockery::on(function (array $context) use ($event): bool {
                    $this->assertSame([
                        'event_id',
                        'status',
                        'provider_error_code',
                        'provider_error_message',
                        'model',
                    ], array_keys($context));
                    $this->assertSame($event->id, $context['event_id']);
                    $this->assertSame(429, $context['status']);
                    $this->assertSame(429, $context['provider_error_code']);
                    $this->assertSame('Rate limit exceeded', $context['provider_error_message']);
                    $this->assertSame('test/vision-model', $context['model']);

                    return true;
                })
            )
            ->once();
    }

    public function test_connection_failure_logs_safe_diagnostics_without_a_response(): void
    {
        Log::spy();

        $event = Events::create([
            'title' => 'Provider connection failure',
            'description' => 'Connection logging test',
        ]);
        $this->createStoredImage($event, 'events/full/connection-failure.png');

        Http::fake(fn () => throw new ConnectionException(" Connection\n timed out "));

        try {
            $this->runJob($event->id);
            $this->fail('The job should throw so the queue can retry it.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Image tags provider request failed.', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')
            ->with(
                'GenerateEventAiTagsJob: OpenRouter request failed',
                Mockery::on(fn (array $context) => $context === [
                    'event_id' => $event->id,
                    'status' => null,
                    'provider_error_code' => null,
                    'provider_error_message' => 'Connection timed out',
                    'model' => 'test/vision-model',
                ])
            )
            ->once();
    }

    public function test_persistence_and_permanent_failure_are_logged_with_job_context(): void
    {
        Log::spy();

        $event = Events::create([
            'title' => 'Persistence failure',
            'description' => 'Persistence logging test',
        ]);
        $this->createStoredImage($event, 'events/full/persistence.png');

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"event_tags":["One"],"images":[]}',
                    ],
                ]],
            ]),
        ]);

        $persistenceException = new RuntimeException('Database write failed');
        $persistence = Mockery::mock(EventAiTagsPersistenceService::class);
        $persistence->shouldReceive('persist')
            ->once()
            ->andThrow($persistenceException);
        $job = new GenerateEventAiTagsJob($event->id);

        try {
            $job->handle(app(GenerateImageTagsService::class), $persistence);
            $this->fail('The persistence exception should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame($persistenceException, $exception);
        }

        Log::shouldHaveReceived('error')
            ->with(
                'GenerateEventAiTagsJob: tags persistence failed',
                Mockery::on(fn (array $context) => $context === [
                    'event_id' => $event->id,
                    'message' => 'Database write failed',
                    'file' => $persistenceException->getFile(),
                    'line' => $persistenceException->getLine(),
                ])
            )
            ->once();

        $job->failed($persistenceException);

        Log::shouldHaveReceived('error')
            ->with(
                'GenerateEventAiTagsJob: permanently failed',
                Mockery::on(fn (array $context) => $context === [
                    'event_id' => $event->id,
                    'job_id' => null,
                    'attempts' => 1,
                    'message' => 'Database write failed',
                ])
            )
            ->once();
    }

    public function test_safe_event_content_leaves_request_ai_flagged_false_without_changing_status(): void
    {
        $event = Events::create([
            'title' => 'Family photography walk',
            'description' => 'A calm outdoor photography event.',
        ]);
        $eventRequestCreate = EventRequestCreate::create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        Http::fake(fn ($request) => $this->isModerationRequest($request)
            ? $this->openRouterContentResponse('{"flagged":false}')
            : $this->openRouterContentResponse('{"event_tags":[],"images":[]}')
        );

        $this->runJob($event->id);

        $eventRequestCreate->refresh();

        $this->assertFalse($eventRequestCreate->ai_flagged);
        $this->assertSame('pending', $eventRequestCreate->status);
        Http::assertSentCount(2);
    }

    public function test_flagged_event_content_updates_only_ai_flagged_without_changing_status(): void
    {
        $event = Events::create([
            'title' => 'Offensive submission',
            'description' => 'The fake provider will flag this content.',
        ]);
        $eventRequestCreate = EventRequestCreate::create([
            'event_id' => $event->id,
            'status' => 'approved',
        ]);

        Http::fake(fn ($request) => $this->isModerationRequest($request)
            ? $this->openRouterContentResponse('{"flagged":true}')
            : $this->openRouterContentResponse('{"event_tags":[],"images":[]}')
        );

        $this->runJob($event->id);

        $eventRequestCreate->refresh();

        $this->assertTrue($eventRequestCreate->ai_flagged);
        $this->assertSame('approved', $eventRequestCreate->status);
    }

    public function test_ai_flagged_remains_saved_when_tag_generation_fails_after_moderation(): void
    {
        $event = Events::create([
            'title' => 'Flagged before tags fail',
            'description' => 'Moderation succeeds and tag generation fails after it.',
        ]);
        $eventRequestCreate = EventRequestCreate::create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        Http::fake(fn ($request) => $this->isModerationRequest($request)
            ? $this->openRouterContentResponse('{"flagged":true}')
            : $this->openRouterContentResponse('')
        );

        try {
            $this->runJob($event->id);
            $this->fail('The tags request should throw after moderation is saved.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Empty response content from provider.', $exception->getMessage());
        }

        $eventRequestCreate->refresh();

        $this->assertTrue($eventRequestCreate->ai_flagged);
        $this->assertSame('pending', $eventRequestCreate->status);
    }

    public function test_moderation_openrouter_failure_does_not_block_processing_or_change_status(): void
    {
        Log::spy();

        $event = Events::create([
            'title' => 'Provider timeout',
            'description' => 'Moderation failure should be non-blocking.',
        ]);
        $eventRequestCreate = EventRequestCreate::create([
            'event_id' => $event->id,
            'status' => 'rejected',
        ]);

        Http::fake(function ($request) {
            if ($this->isModerationRequest($request)) {
                throw new ConnectionException(" Moderation\n timed out ");
            }

            return $this->openRouterContentResponse('{"event_tags":[],"images":[]}');
        });

        $this->runJob($event->id);

        $eventRequestCreate->refresh();

        $this->assertFalse($eventRequestCreate->ai_flagged);
        $this->assertSame('rejected', $eventRequestCreate->status);
        $this->assertDatabaseHas('events', ['id' => $event->id]);

        Log::shouldHaveReceived('error')
            ->with(
                'Event content moderation: OpenRouter request failed',
                Mockery::on(fn (array $context) => $context === [
                    'event_id' => $event->id,
                    'status' => null,
                    'provider_error_code' => null,
                    'provider_error_message' => 'Moderation timed out',
                    'model' => 'test/vision-model',
                ])
            )
            ->once();
    }

    public function test_invalid_moderation_json_does_not_block_processing_or_change_status(): void
    {
        Log::spy();

        $event = Events::create([
            'title' => 'Invalid moderation JSON',
            'description' => 'The moderation response is malformed.',
        ]);
        $eventRequestCreate = EventRequestCreate::create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        Http::fake(fn ($request) => $this->isModerationRequest($request)
            ? $this->openRouterContentResponse('not-json')
            : $this->openRouterContentResponse('{"event_tags":[],"images":[]}')
        );

        $this->runJob($event->id);

        $eventRequestCreate->refresh();

        $this->assertFalse($eventRequestCreate->ai_flagged);
        $this->assertSame('pending', $eventRequestCreate->status);
        $this->assertDatabaseHas('events', ['id' => $event->id]);

        Log::shouldHaveReceived('error')
            ->with(
                'AI moderation processing failed',
                Mockery::on(fn (array $context) => $context['event_id'] === $event->id
                    && $context['event_request_create_id'] === $eventRequestCreate->id
                    && $context['exception_class'] === \JsonException::class
                    && is_string($context['message'])
                    && $context['message'] !== '')
            )
            ->once();
    }

    private function runJob(int $eventId): void
    {
        (new GenerateEventAiTagsJob($eventId))->handle(
            app(GenerateImageTagsService::class),
            app(EventAiTagsPersistenceService::class)
        );
    }

    private function createStoredImage(Events $event, string $path): EventsImges
    {
        Storage::disk('public')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return EventsImges::create([
            'event_id' => $event->id,
            'type' => 'image',
            'full_url' => $path,
            'preview_url' => $path,
            'is_active' => 1,
        ]);
    }

    private function isModerationRequest($request): bool
    {
        $prompt = data_get($request->data(), 'messages.0.content.0.text', '');

        return is_string($prompt)
            && str_contains($prompt, 'strict content moderation classifier');
    }

    private function openRouterContentResponse(string $content)
    {
        return Http::response([
            'choices' => [[
                'message' => [
                    'content' => $content,
                ],
            ]],
        ]);
    }
}
