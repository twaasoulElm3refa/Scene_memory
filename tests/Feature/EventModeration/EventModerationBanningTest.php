<?php

namespace Tests\Feature\EventModeration;

use App\Jobs\ReviewEventRequestWithAi;
use App\Mail\AccountBannedMail;
use App\Mail\ApproveMail;
use App\Mail\EventNeedsManualReviewMail;
use App\Mail\RejectMail;
use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\User;
use App\Services\EventModeration\AiModerationResponseValidator;
use App\Services\EventModeration\EventModerationPayloadBuilder;
use App\Services\EventModeration\EventRequestModerationService;
use App\Services\EventModeration\ModerationDecision;
use App\Services\EventModeration\N8nEventModerationClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class EventModerationBanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Bus::fake([ReviewEventRequestWithAi::class]);
        config()->set('event_moderation.admin_email', 'admin@example.com');
        config()->set('event_moderation.auto_decision_threshold', 0.70);
    }

    /** Scenario 1: Normal approved event -> Approved, no ban. */
    public function test_scenario_01_normal_approved_event_does_not_ban_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();

        $decision = $this->decision(
            decision: 'approved',
            confidence: 0.95,
            reason: 'Legitimate cultural gathering.',
            flags: [],
            reasonCode: 'none',
            banRecommended: false
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertTrue($event->fresh()->is_active);
        $this->assertFalse($owner->fresh()->isBanned());
        $this->assertNull($owner->fresh()->banned_at);
        $this->assertNull($owner->fresh()->ban_reason);

        Mail::assertQueued(ApproveMail::class, 1);
        Mail::assertNotQueued(RejectMail::class);
        Mail::assertNotQueued(AccountBannedMail::class);

        Bus::assertDispatched(
            ReviewEventRequestWithAi::class,
            fn (ReviewEventRequestWithAi $job): bool => $job->requestId === $request->id && $job->translationOnly
        );
    }

    /** Scenario 2: Confirmed military content -> Rejected, owner banned. */
    public function test_scenario_02_confirmed_military_content_rejects_and_bans_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        $reason = 'Submission promotes unauthorized armed paramilitary training and weapons exhibition.';

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.98,
            reason: $reason,
            flags: ['weapons', 'military'],
            reasonCode: 'military_content',
            policyCategories: ['military_content'],
            banRecommended: true
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($event->fresh()->is_active);

        $refreshedOwner = $owner->fresh();
        $this->assertTrue($refreshedOwner->isBanned());
        $this->assertNotNull($refreshedOwner->banned_at);
        $this->assertSame($reason, $refreshedOwner->ban_reason);

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertQueued(AccountBannedMail::class, 1);
        Bus::assertNotDispatched(
            ReviewEventRequestWithAi::class,
            fn (ReviewEventRequestWithAi $job): bool => $job->translationOnly
        );
    }

    /** Scenario 3: Confirmed political content -> Rejected, owner banned. */
    public function test_scenario_03_confirmed_political_content_rejects_and_bans_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        $reason = 'Submission organizes a partisan political rally and election mobilization campaign.';

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.94,
            reason: $reason,
            flags: ['politics'],
            reasonCode: 'political_content',
            policyCategories: ['political_content'],
            banRecommended: true
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($event->fresh()->is_active);

        $refreshedOwner = $owner->fresh();
        $this->assertTrue($refreshedOwner->isBanned());
        $this->assertSame($reason, $refreshedOwner->ban_reason);

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertQueued(AccountBannedMail::class, 1);
    }

    /** Scenario 4: Confirmed sexual content -> Rejected, owner banned. */
    public function test_scenario_04_confirmed_sexual_content_rejects_and_bans_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        $reason = 'Submission advertises explicit sexual services and adult pornography.';

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.99,
            reason: $reason,
            flags: ['explicit_sexual'],
            reasonCode: 'sexual_content',
            policyCategories: ['sexual_content'],
            banRecommended: true
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($event->fresh()->is_active);

        $refreshedOwner = $owner->fresh();
        $this->assertTrue($refreshedOwner->isBanned());
        $this->assertSame($reason, $refreshedOwner->ban_reason);

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertQueued(AccountBannedMail::class, 1);
    }

    /** Scenario 5: Confirmed obscene/profane content -> Rejected, owner banned. */
    public function test_scenario_05_confirmed_obscene_profane_content_rejects_and_bans_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        $reason = 'Submission contains aggressive abusive vulgarities and obscene defamation.';

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.96,
            reason: $reason,
            flags: ['profanity'],
            reasonCode: 'profanity_obscene_content',
            policyCategories: ['profanity_obscene_content'],
            banRecommended: true
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($event->fresh()->is_active);

        $refreshedOwner = $owner->fresh();
        $this->assertTrue($refreshedOwner->isBanned());
        $this->assertSame($reason, $refreshedOwner->ban_reason);

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertQueued(AccountBannedMail::class, 1);
    }

    /** Scenario 6: Rejected spam -> Rejected, owner NOT banned. */
    public function test_scenario_06_rejected_spam_does_not_ban_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        $reason = 'Unsolicited cryptocurrency promotional spam.';

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.95,
            reason: $reason,
            flags: ['spam'],
            reasonCode: 'spam',
            policyCategories: ['spam'],
            banRecommended: false
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($event->fresh()->is_active);
        $this->assertFalse($owner->fresh()->isBanned());

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertNotQueued(AccountBannedMail::class);
    }

    /** Scenario 7: Rejected duplicate event -> Rejected, owner NOT banned. */
    public function test_scenario_07_rejected_duplicate_does_not_ban_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        $reason = 'This event is an exact duplicate of previously posted event #102.';

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.91,
            reason: $reason,
            flags: ['duplicate'],
            reasonCode: 'duplicate',
            policyCategories: ['duplicate'],
            banRecommended: false
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($event->fresh()->is_active);
        $this->assertFalse($owner->fresh()->isBanned());

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertNotQueued(AccountBannedMail::class);
    }

    /** Scenario 8: Rejected incomplete event -> Rejected, owner NOT banned. */
    public function test_scenario_08_rejected_incomplete_does_not_ban_owner(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        $reason = 'The event details lack valid dates and location information.';

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.88,
            reason: $reason,
            flags: ['insufficient_information'],
            reasonCode: 'insufficient_information',
            policyCategories: ['insufficient_information'],
            banRecommended: false
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($event->fresh()->is_active);
        $this->assertFalse($owner->fresh()->isBanned());

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertNotQueued(AccountBannedMail::class);
    }

    /** Scenario 9: Manual review -> No ban, no translation. */
    public function test_scenario_09_manual_review_does_not_ban_and_does_not_translate(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();

        $decision = $this->decision(
            decision: 'manual_review',
            confidence: 0.65,
            reason: 'Historical claim requires curator verification.',
            flags: ['needs_curation'],
            reasonCode: 'none',
            banRecommended: false
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('manual_review', $request->fresh()->ai_decision);
        $this->assertFalse($event->fresh()->is_active);
        $this->assertFalse($owner->fresh()->isBanned());

        Mail::assertQueued(EventNeedsManualReviewMail::class, 1);
        Mail::assertNotQueued(ApproveMail::class);
        Mail::assertNotQueued(RejectMail::class);
        Mail::assertNotQueued(AccountBannedMail::class);

        Bus::assertNotDispatched(
            ReviewEventRequestWithAi::class,
            fn (ReviewEventRequestWithAi $job): bool => $job->translationOnly
        );
    }

    /** Scenario 10: Ambiguous potentially prohibited content -> Manual review, no automatic ban. */
    public function test_scenario_10_ambiguous_prohibited_content_downgrades_to_manual_review_without_ban(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();

        // AI attempted rejection with confidence 0.58 on political content
        $validated = app(AiModerationResponseValidator::class)->validate([
            'decision' => 'rejected',
            'confidence' => 0.58,
            'reason' => 'Mention of a political candidate might be informational or partisan rally.',
            'reason_code' => 'political_content',
            'policy_categories' => ['political_content'],
            'ban_recommended' => true,
            'flags' => ['ambiguous_politics'],
        ]);

        $this->assertSame('manual_review', $validated->decision);
        $this->assertFalse($validated->banRecommended);
        $this->assertContains('below_auto_decision_threshold', $validated->flags);

        $applied = $this->service()->applyAiDecision($request->id, $validated);

        $this->assertTrue($applied);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertFalse($owner->fresh()->isBanned());
        Mail::assertQueued(EventNeedsManualReviewMail::class, 1);
        Mail::assertNotQueued(AccountBannedMail::class);
    }

    /** Scenario 11: Malformed AI JSON -> Safe pending/manual review. */
    public function test_scenario_11_malformed_ai_json_fails_safely_without_altering_state_or_banning(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();
        config()->set('event_moderation.n8n.webhook_secret', str_repeat('s', 32));
        config()->set('event_moderation.n8n.webhook_url', 'https://n8n.test/webhook/scemory-event-moderation');

        Http::fake([
            'https://n8n.test/*' => Http::response(['invalid' => 'json structure without decision'], 200),
        ]);

        $job = new ReviewEventRequestWithAi($request->id);

        try {
            $job->handle(
                $this->service(),
                app(EventModerationPayloadBuilder::class),
                app(N8nEventModerationClient::class),
                app(AiModerationResponseValidator::class),
            );
            $this->fail('Malformed JSON response must throw RuntimeException.');
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertNull($request->fresh()->ai_decision);
        $this->assertFalse($event->fresh()->is_active);
        $this->assertFalse($owner->fresh()->isBanned());
        Mail::assertNothingQueued();
    }

    /** Scenario 12: Spoofed user ID -> Cannot ban another user, and admins cannot be banned. */
    public function test_scenario_12_spoofed_user_id_in_payload_does_not_ban_unrelated_user(): void
    {
        $innocentUser = User::factory()->create([
            'name' => 'Innocent User',
            'email' => 'innocent@example.com',
            'role' => 'user',
        ]);
        [$event, $request, $realOwner] = $this->pendingRequest();

        // Craft decision pretending user_id was 9999 or innocentUser
        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.99,
            reason: 'Prohibited military recruitment event.',
            flags: ['military'],
            reasonCode: 'military_content',
            policyCategories: ['military_content'],
            banRecommended: true
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertFalse($innocentUser->fresh()->isBanned());
        $this->assertTrue($realOwner->fresh()->isBanned());
    }

    public function test_scenario_12b_admin_owner_is_never_banned_by_ai(): void
    {
        $admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin_owner@example.com',
            'role' => 'admin',
        ]);
        $event = Events::query()->create([
            'user_id' => $admin->id,
            'title' => 'Admin test event',
            'description' => 'Admin test description',
            'is_active' => false,
        ]);
        $request = EventRequestCreate::query()->create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.99,
            reason: 'Hostile takeover attempt classification.',
            flags: ['military'],
            reasonCode: 'military_content',
            policyCategories: ['military_content'],
            banRecommended: true
        );

        $applied = $this->service()->applyAiDecision($request->id, $decision);

        $this->assertTrue($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($admin->fresh()->isBanned());
        Mail::assertNotQueued(AccountBannedMail::class);
    }

    /** Scenario 13: Repeated moderation callback -> No duplicate side effects. */
    public function test_scenario_13_repeated_moderation_callback_is_idempotent(): void
    {
        [$event, $request, $owner] = $this->pendingRequest();

        $decision = $this->decision(
            decision: 'rejected',
            confidence: 0.98,
            reason: 'Prohibited sexual content.',
            flags: ['sexual_content'],
            reasonCode: 'sexual_content',
            policyCategories: ['sexual_content'],
            banRecommended: true
        );

        $this->assertTrue($this->service()->applyAiDecision($request->id, $decision));
        $initialBannedAt = $owner->fresh()->banned_at;
        $initialBanReason = $owner->fresh()->ban_reason;

        // Second delivery with different reason text
        $secondDecision = $this->decision(
            decision: 'rejected',
            confidence: 0.98,
            reason: 'Different reason text on retry.',
            flags: ['sexual_content'],
            reasonCode: 'sexual_content',
            policyCategories: ['sexual_content'],
            banRecommended: true
        );

        $this->assertFalse($this->service()->applyAiDecision($request->id, $secondDecision));

        $refreshed = $owner->fresh();
        $this->assertSame($initialBannedAt->toIso8601String(), $refreshed->banned_at->toIso8601String());
        $this->assertSame($initialBanReason, $refreshed->ban_reason);

        Mail::assertQueued(RejectMail::class, 1);
        Mail::assertQueued(AccountBannedMail::class, 1);
    }

    /** Scenario 14: Banned user tries creating another event -> Request blocked. */
    public function test_scenario_14_banned_user_cannot_create_event(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => 'Prohibited political content.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/events/create', [
            'title' => 'Attempted New Event',
            'description' => 'Should be rejected before creation',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('events', [
            'title' => 'Attempted New Event',
        ]);
    }

    /** Scenario 15: Banned user accesses a protected API directly -> Access blocked. */
    public function test_scenario_15_banned_user_accessing_protected_api_is_blocked(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => 'Violated platform guidelines regarding obscene content.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users/profile');

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'ban_reason' => 'Violated platform guidelines regarding obscene content.',
        ]);
    }

    /** Scenario 16: Authorized administrator unbans a user -> Restrictions removed. */
    public function test_scenario_16_authorized_admin_can_unban_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bannedUser = User::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => 'Previously banned by mistake.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/users/{$bannedUser->id}/unban");

        $response->assertSuccessful();

        $refreshed = $bannedUser->fresh();
        $this->assertFalse($refreshed->isBanned());
        $this->assertNull($refreshed->banned_at);
        $this->assertNull($refreshed->ban_reason);

        // User can now access protected APIs
        Sanctum::actingAs($refreshed);
        $profileResponse = $this->getJson('/api/v1/users/profile');
        $profileResponse->assertSuccessful();
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
            'role' => 'user',
            'is_banned' => false,
            'is_active' => true,
        ]);
        $event = Events::query()->create([
            'user_id' => $user->id,
            'title' => 'Test event '.uniqid(),
            'description' => 'Test event description for moderation.',
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
     * @param  array<int, string>  $flags
     * @param  array<int, string>  $policyCategories
     */
    private function decision(
        string $decision,
        float $confidence,
        string $reason,
        array $flags = [],
        ?string $reasonCode = null,
        array $policyCategories = [],
        bool $banRecommended = false,
    ): ModerationDecision {
        $raw = compact('decision', 'confidence', 'reason', 'flags', 'reasonCode', 'policyCategories', 'banRecommended');

        return new ModerationDecision(
            decision: $decision,
            confidence: $confidence,
            reason: $reason,
            flags: $flags,
            rawResponse: $raw,
            workflowExecutionId: 'execution-'.uniqid(),
            reasonCode: $reasonCode,
            policyCategories: $policyCategories,
            banRecommended: $banRecommended,
        );
    }
}
