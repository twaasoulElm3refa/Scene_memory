<?php

namespace Tests\Feature\EventModeration;

use App\Jobs\ReviewEventRequestWithAi;
use App\Models\Categories;
use App\Models\CategoreyTranslations;
use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\EventTranslations;
use App\Models\User;
use App\Services\EventModeration\AiModerationResponseValidator;
use App\Services\EventModeration\EventModerationPayloadBuilder;
use App\Services\EventModeration\EventRequestModerationService;
use App\Services\EventModeration\ModerationDecision;
use App\Services\EventModeration\N8nEventModerationClient;
use App\Support\EventTranslationLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EventModerationTranslationTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Bus::fake([ReviewEventRequestWithAi::class]);
        $this->webhookSecret = str_repeat('s', 32);
        config()->set('event_moderation.n8n.webhook_secret', $this->webhookSecret);
        config()->set('event_moderation.n8n.webhook_url', 'https://n8n.test/webhook/scemory-event-moderation');
        config()->set('event_moderation.admin_email', 'admin@example.com');
        config()->set('event_moderation.auto_decision_threshold', 0.70);
    }

    /** Scenario 17: Approved event -> Translation triggered only after approval is persisted. */
    public function test_scenario_17_approved_event_triggers_translation_only_after_approval_persisted(): void
    {
        [$event, $request] = $this->pendingRequest();

        $decision = new ModerationDecision(
            decision: 'approved',
            confidence: 0.90,
            reason: 'Legitimate art exhibition.',
            flags: [],
            rawResponse: ['decision' => 'approved'],
            workflowExecutionId: 'exec-1',
            reasonCode: 'none',
            policyCategories: [],
            banRecommended: false
        );

        $this->service()->applyAiDecision($request->id, $decision);

        // Assert approval is persisted in database
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertTrue($event->fresh()->is_active);

        // Assert translation job was dispatched
        Bus::assertDispatched(
            ReviewEventRequestWithAi::class,
            fn (ReviewEventRequestWithAi $job): bool => $job->requestId === $request->id && $job->translationOnly
        );
    }

    /** Scenario 18: Approved event -> Correct Events ID used. */
    public function test_scenario_18_approved_event_translation_uses_events_model_id(): void
    {
        [$event, $request] = $this->pendingRequest();
        $request->update(['status' => 'approved']);
        $event->update(['is_active' => true]);

        Http::fake([
            'https://n8n.test/*' => Http::response(['accepted' => true], 202),
        ]);

        $job = new ReviewEventRequestWithAi($request->id, translationOnly: true);
        $job->handle(
            $this->service(),
            app(EventModerationPayloadBuilder::class),
            app(N8nEventModerationClient::class),
            app(AiModerationResponseValidator::class),
        );

        Http::assertSent(function (HttpRequest $sent) use ($event, $request): bool {
            return $sent['mode'] === 'translation'
                && $sent['request_id'] === $request->id
                && $sent['event']['id'] === $event->id;
        });

        // Test sending translations to Laravel endpoint uses Events ID:
        $translations = $this->generate13Translations($event->title, $event->description);
        $response = $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('event_translations', [
            'event_id' => $event->id,
            'locale' => 'ar',
        ]);
    }

    /** Scenario 19: Approved event -> Exactly 13 required locale records. */
    public function test_scenario_19_approved_event_stores_exactly_13_locale_records(): void
    {
        [$event] = $this->pendingRequest();
        $translations = $this->generate13Translations($event->title, $event->description);

        $response = $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations,
        ]);

        $response->assertSuccessful();
        $this->assertCount(13, EventTranslations::query()->where('event_id', $event->id)->get());
    }

    /** Scenario 20: Every locale -> Correct title and description. */
    public function test_scenario_20_every_locale_has_correct_title_and_description(): void
    {
        [$event] = $this->pendingRequest();
        $translations = $this->generate13Translations($event->title, $event->description);

        $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations,
        ])->assertSuccessful();

        foreach (EventTranslationLocales::ALL as $locale) {
            $this->assertDatabaseHas('event_translations', [
                'event_id' => $event->id,
                'locale' => $locale,
                'title' => "Title in {$locale}",
                'description' => "Description in {$locale}",
            ]);
        }
    }

    /** Scenario 21: Original language -> Original text preserved. */
    public function test_scenario_21_original_arabic_text_is_preserved_verbatim(): void
    {
        [$event] = $this->pendingRequest();
        $originalTitle = 'مهرجان الرياض التراثي السنوي';
        $originalDesc = 'معرض ثقافي تراثي سنوي يقام في الرياض مع مشاركات فنية أصيلة.';
        $event->update([
            'title' => $originalTitle,
            'description' => $originalDesc,
        ]);

        $translations = $this->generate13Translations($originalTitle, $originalDesc, arOriginal: true);

        $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations,
        ])->assertSuccessful();

        $arRecord = EventTranslations::query()
            ->where('event_id', $event->id)
            ->where('locale', 'ar')
            ->firstOrFail();

        $this->assertSame($originalTitle, $arRecord->title);
        $this->assertSame($originalDesc, $arRecord->description);
    }

    /** Scenario 22: Rejected event -> Zero new translations. */
    public function test_scenario_22_rejected_event_results_in_zero_translations(): void
    {
        [$event, $request] = $this->pendingRequest();

        $decision = new ModerationDecision(
            decision: 'rejected',
            confidence: 0.95,
            reason: 'Irrelevant spam.',
            flags: ['spam'],
            rawResponse: ['decision' => 'rejected'],
            workflowExecutionId: 'exec-2',
            reasonCode: 'spam',
            policyCategories: ['spam'],
            banRecommended: false
        );

        $this->service()->applyAiDecision($request->id, $decision);

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertDatabaseCount('event_translations', 0);
        Bus::assertNotDispatched(
            ReviewEventRequestWithAi::class,
            fn (ReviewEventRequestWithAi $job): bool => $job->translationOnly
        );
    }

    /** Scenario 23: Banned event owner -> Rejected event never translated. */
    public function test_scenario_23_banned_event_owner_event_is_never_translated(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();

        $decision = new ModerationDecision(
            decision: 'rejected',
            confidence: 0.99,
            reason: 'Explicit sexual content.',
            flags: ['sexual'],
            rawResponse: ['decision' => 'rejected'],
            workflowExecutionId: 'exec-3',
            reasonCode: 'sexual_content',
            policyCategories: ['sexual_content'],
            banRecommended: true
        );

        $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($owner->fresh()->isBanned());
        $this->assertDatabaseCount('event_translations', 0);
        Bus::assertNotDispatched(
            ReviewEventRequestWithAi::class,
            fn (ReviewEventRequestWithAi $job): bool => $job->translationOnly
        );
    }

    /** Scenario 24: Manual review -> Zero new translations. */
    public function test_scenario_24_manual_review_results_in_zero_translations(): void
    {
        [$event, $request] = $this->pendingRequest();

        $decision = new ModerationDecision(
            decision: 'manual_review',
            confidence: 0.61,
            reason: 'Unverifiable dates require curator review.',
            flags: ['unverified'],
            rawResponse: ['decision' => 'manual_review'],
            workflowExecutionId: 'exec-4',
            reasonCode: 'none',
            policyCategories: [],
            banRecommended: false
        );

        $this->service()->applyAiDecision($request->id, $decision);

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertDatabaseCount('event_translations', 0);
        Bus::assertNotDispatched(
            ReviewEventRequestWithAi::class,
            fn (ReviewEventRequestWithAi $job): bool => $job->translationOnly
        );
    }

    /** Scenario 25: OpenRouter Translation fails -> Recoverable failure, without changing moderation status. */
    public function test_scenario_25_openrouter_translation_outage_fails_recoverably_without_altering_approval(): void
    {
        [$event, $request] = $this->pendingRequest();
        $request->update(['status' => 'approved']);
        $event->update(['is_active' => true]);

        Http::fake([
            'https://n8n.test/*' => Http::response(['message' => 'OpenRouter translation service unavailable / rate limited'], 503),
        ]);

        $job = new ReviewEventRequestWithAi($request->id, translationOnly: true);

        try {
            $job->handle(
                $this->service(),
                app(EventModerationPayloadBuilder::class),
                app(N8nEventModerationClient::class),
                app(AiModerationResponseValidator::class),
            );
            $this->fail('Translation failure must throw RuntimeException to allow queue retries.');
        } catch (RuntimeException) {
            // Expected
        }

        // Event remains approved
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertTrue($event->fresh()->is_active);
    }

    /** Scenario 26: Retry -> No duplicate translation rows. */
    public function test_scenario_26_retry_does_not_create_duplicate_translation_rows(): void
    {
        [$event] = $this->pendingRequest();
        $translations = $this->generate13Translations($event->title, $event->description);

        for ($i = 0; $i < 2; $i++) {
            $this->withHeaders([
                'X-Scemory-Webhook-Secret' => $this->webhookSecret,
            ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
                'event_id' => $event->id,
                'translations' => $translations,
            ])->assertSuccessful();
        }

        $this->assertCount(13, EventTranslations::query()->where('event_id', $event->id)->get());
    }

    /** Scenario 27: Already translated locale -> No unnecessary retranslations / safe update. */
    public function test_scenario_27_already_translated_locale_is_safely_updated(): void
    {
        [$event] = $this->pendingRequest();
        $translations1 = $this->generate13Translations($event->title, $event->description);

        $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations1,
        ])->assertSuccessful();

        $translations2 = $translations1;
        $translations2[1]['title'] = 'Updated English Title';

        $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations2,
        ])->assertSuccessful();

        $this->assertCount(13, EventTranslations::query()->where('event_id', $event->id)->get());
        $this->assertDatabaseHas('event_translations', [
            'event_id' => $event->id,
            'locale' => 'en',
            'title' => 'Updated English Title',
        ]);
    }

    /** Scenario 28: Arabic/Persian/Urdu -> Correct Unicode and RTL content. */
    public function test_scenario_28_arabic_persian_urdu_rtl_unicode_stored_intact(): void
    {
        [$event] = $this->pendingRequest();
        $translations = $this->generate13Translations($event->title, $event->description);

        $arabicText = 'مهرجان الثقافة والفنون الأصيلة';
        $persianText = 'جشنواره فرهنگ و هنرهای اصیل ایرانی';
        $urduText = 'ثقافت اور روایتی فنون کا سالانہ میلہ';

        foreach ($translations as &$t) {
            if ($t['locale'] === 'ar') {
                $t['title'] = $arabicText;
                $t['description'] = $arabicText;
            } elseif ($t['locale'] === 'fa') {
                $t['title'] = $persianText;
                $t['description'] = $persianText;
            } elseif ($t['locale'] === 'ur') {
                $t['title'] = $urduText;
                $t['description'] = $urduText;
            }
        }
        unset($t);

        $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations,
        ])->assertSuccessful();

        $this->assertDatabaseHas('event_translations', [
            'event_id' => $event->id,
            'locale' => 'ar',
            'title' => $arabicText,
        ]);
        $this->assertDatabaseHas('event_translations', [
            'event_id' => $event->id,
            'locale' => 'fa',
            'title' => $persianText,
        ]);
        $this->assertDatabaseHas('event_translations', [
            'event_id' => $event->id,
            'locale' => 'ur',
            'title' => $urduText,
        ]);
    }

    /** Scenario 29: Existing Category/SubCategory/City/Country translations -> Unaffected. */
    public function test_scenario_29_category_and_other_translations_are_unaffected(): void
    {
        $category = Categories::query()->create([
            'name' => 'Music & Arts',
        ]);
        $categoryTranslation = CategoreyTranslations::query()->create([
            'category_id' => $category->id,
            'locale' => 'ar',
            'name' => 'الموسيقى والفنون',
        ]);

        [$event] = $this->pendingRequest();
        $translations = $this->generate13Translations($event->title, $event->description);

        $this->withHeaders([
            'X-Scemory-Webhook-Secret' => $this->webhookSecret,
        ])->postJson("/api/v1/moderation/events/{$event->id}/translations", [
            'event_id' => $event->id,
            'translations' => $translations,
        ])->assertSuccessful();

        // Category translation remains intact
        $this->assertDatabaseHas('categorey_translations', [
            'id' => $categoryTranslation->id,
            'locale' => 'ar',
            'name' => 'الموسيقى والفنون',
        ]);
    }

    /** Scenario 30: OpenRouter translation configuration missing -> Clear diagnostic failure, no fabricated success. */
    public function test_scenario_30_openrouter_translation_model_missing_results_in_diagnostic_failure(): void
    {
        config()->set('event_moderation.n8n.base_url', 'https://n8n.test');
        config()->set('event_moderation.n8n.api_key', 'n8n-key');
        config()->set('event_moderation.openrouter.api_key', 'openrouter-key');
        config()->set('event_moderation.openrouter.translation_model', ''); // missing!

        $this->artisan('scemory:setup-n8n')
            ->expectsOutputToContain('OPENROUTER_TRANSLATION_MODEL is required.')
            ->assertFailed();
    }

    private function service(): EventRequestModerationService
    {
        return app(EventRequestModerationService::class);
    }

    /** @return array{0: Events, 1: EventRequestCreate, 2: User} */
    private function pendingRequest(): array
    {
        $user = User::factory()->create([
            'name' => 'Event Owner '.uniqid(),
            'email' => 'owner_'.uniqid().'@example.com',
            'is_banned' => false,
            'is_active' => true,
        ]);
        $event = Events::query()->create([
            'user_id' => $user->id,
            'title' => 'Test event '.uniqid(),
            'description' => 'Test event description for translation.',
            'is_active' => false,
            'is_real' => true,
            'is_historical' => false,
        ]);
        $request = EventRequestCreate::query()->create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        return [$event, $request, $user];
    }

    /**
     * @return array<int, array{locale: string, title: string, description: string}>
     */
    private function generate13Translations(string $originalTitle, string $originalDesc, bool $arOriginal = false): array
    {
        return collect(EventTranslationLocales::ALL)
            ->map(function (string $locale) use ($originalTitle, $originalDesc, $arOriginal): array {
                if ($locale === 'ar' && $arOriginal) {
                    return [
                        'locale' => 'ar',
                        'title' => $originalTitle,
                        'description' => $originalDesc,
                    ];
                }

                return [
                    'locale' => $locale,
                    'title' => "Title in {$locale}",
                    'description' => "Description in {$locale}",
                ];
            })
            ->all();
    }
}
