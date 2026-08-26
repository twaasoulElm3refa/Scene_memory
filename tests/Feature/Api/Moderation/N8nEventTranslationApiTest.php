<?php

namespace Tests\Feature\Api\Moderation;

use App\Models\Events;
use App\Support\EventTranslationLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class N8nEventTranslationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = str_repeat('n', 32);
        config()->set('event_moderation.n8n.webhook_secret', $this->secret);
    }

    public function test_translation_endpoint_requires_the_existing_n8n_secret(): void
    {
        $event = $this->event();

        $this->postJson($this->endpoint($event), $this->payload($event))
            ->assertUnauthorized();

        $this->assertDatabaseCount('event_translations', 0);
    }

    public function test_n8n_can_store_all_locales_idempotently(): void
    {
        $event = $this->event();
        $payload = $this->payload($event);

        $this->withHeader('X-Scemory-Webhook-Secret', $this->secret)
            ->postJson($this->endpoint($event), $payload)
            ->assertOk()
            ->assertJsonPath('event_id', $event->id)
            ->assertJsonPath('translations_saved', 13);

        $this->assertDatabaseCount('event_translations', 13);
        $this->assertEqualsCanonicalizing(
            EventTranslationLocales::ALL,
            $event->translations()->pluck('locale')->all()
        );

        $payload['translations'][1]['title'] = 'Updated English title';

        $this->withHeader('X-Scemory-Webhook-Secret', $this->secret)
            ->postJson($this->endpoint($event), $payload)
            ->assertOk();

        $this->assertDatabaseCount('event_translations', 13);
        $this->assertDatabaseHas('event_translations', [
            'event_id' => $event->id,
            'locale' => 'en',
            'title' => 'Updated English title',
        ]);
    }

    public function test_translation_endpoint_requires_exactly_the_supported_locale_set(): void
    {
        $event = $this->event();
        $payload = $this->payload($event);
        array_pop($payload['translations']);

        $this->withHeader('X-Scemory-Webhook-Secret', $this->secret)
            ->postJson($this->endpoint($event), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('translations');

        $this->assertDatabaseCount('event_translations', 0);
    }

    public function test_translation_event_id_must_match_the_route_event(): void
    {
        $event = $this->event();
        $payload = $this->payload($event);
        $payload['event_id'] = $event->id + 1;

        $this->withHeader('X-Scemory-Webhook-Secret', $this->secret)
            ->postJson($this->endpoint($event), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');

        $this->assertDatabaseCount('event_translations', 0);
    }

    private function event(): Events
    {
        return Events::query()->create([
            'title' => 'زلزال تركيا وسوريا 2023',
            'description' => 'في 6 فبراير 2023 وقع الزلزال.',
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Events $event): array
    {
        return [
            'event_id' => $event->id,
            'translations' => collect(EventTranslationLocales::ALL)
                ->map(fn (string $locale): array => [
                    'locale' => $locale,
                    'title' => $locale === 'ar' ? $event->title : "{$locale} title",
                    'description' => $locale === 'ar'
                        ? $event->description
                        : "{$locale} description",
                ])
                ->all(),
        ];
    }

    private function endpoint(Events $event): string
    {
        return "/api/v1/moderation/events/{$event->id}/translations";
    }
}
