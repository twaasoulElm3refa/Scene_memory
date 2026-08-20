<?php

namespace Tests\Feature\Api\Events;

use App\Jobs\ProcessEventImageJob;
use App\Jobs\ProcessEventVideoJob;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventCreationRequirementsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('creationEndpoints')]
    public function test_minimum_requirements_are_consistent_for_every_primary_creation_flow(
        string $uri,
        string $role,
        bool $historical
    ): void {
        Sanctum::actingAs(User::factory()->create(['role' => $role]));

        foreach (['image', 'video', 'mixed'] as $mediaKind) {
            Bus::fake();

            $response = $this->post($uri, [
                'title' => 'Minimum event '.$mediaKind,
                'description' => 'Only the required textual fields are present.',
                'urls' => $this->mediaFor($mediaKind),
            ]);

            $response->assertOk();
            $eventId = (int) $response->json('data.id');

            $this->assertDatabaseHas('events', [
                'id' => $eventId,
                'is_historical' => $historical,
            ]);

            Bus::assertBatched(function (PendingBatch $batch) use ($mediaKind): bool {
                $jobs = $batch->jobs->values();
                $expectedCount = $mediaKind === 'mixed' ? 2 : 1;

                $this->assertCount($expectedCount, $jobs);

                if ($mediaKind !== 'video') {
                    $this->assertTrue($jobs->contains(fn ($job) => $job instanceof ProcessEventImageJob));
                }

                if ($mediaKind !== 'image') {
                    $this->assertTrue($jobs->contains(fn ($job) => $job instanceof ProcessEventVideoJob));
                }

                return true;
            });
        }
    }

    #[DataProvider('creationEndpoints')]
    public function test_title_description_and_media_are_the_only_required_creation_inputs(
        string $uri,
        string $role,
        bool $historical
    ): void {
        Sanctum::actingAs(User::factory()->create(['role' => $role]));
        Bus::fake();

        $this->post($uri, [
            'title' => 'No media',
            'description' => 'This must fail.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['media']);

        $this->post($uri, [
            'description' => 'No title.',
            'urls' => $this->mediaFor('image'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['title']);

        $this->post($uri, [
            'title' => 'No description',
            'urls' => $this->mediaFor('video'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['description']);

        $this->post($uri, [
            'title' => 'Optional metadata omitted',
            'description' => 'Media descriptions and manual tags are intentionally absent.',
            'urls' => $this->mediaFor('image'),
            'photo_descriptions' => [''],
            'photo_tags_json' => [json_encode(['tags_id' => [], 'new_tags' => []])],
            'tags_id' => [],
            'new_tags' => [],
        ])->assertOk();
    }

    public static function creationEndpoints(): array
    {
        return [
            'normal user event' => ['/api/v1/events/create/user', 'user', false],
            'historical user event' => ['/api/v1/events/historic/user', 'user', true],
            'admin event' => ['/api/v1/events/create', 'admin', false],
            'admin historical event' => ['/api/v1/events/historic', 'admin', true],
            'legacy dashboard event' => ['/api/v1/user-dshboard/create/Event', 'user', false],
            'legacy create event' => ['/api/v1/create', 'user', false],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function mediaFor(string $kind): array
    {
        $image = UploadedFile::fake()->createWithContent(
            'event.png',
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
            )
        );
        $video = UploadedFile::fake()->create('event.mp4', 100, 'video/mp4');

        return match ($kind) {
            'image' => [$image],
            'video' => [$video],
            default => [$image, $video],
        };
    }
}
