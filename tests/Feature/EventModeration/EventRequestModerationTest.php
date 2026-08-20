<?php

namespace Tests\Feature\EventModeration;

use App\Jobs\ReviewEventRequestWithAi;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EventRequestModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config()->set('event_moderation.admin_email', 'admin@example.com');
        config()->set('event_moderation.auto_decision_threshold', 0.85);
    }

    public function test_ai_approval_activates_event_and_queues_existing_approval_mail(): void
    {
        [$event, $request] = $this->pendingRequest();

        $applied = $this->service()->applyAiDecision(
            $request->id,
            $this->decision('approved', 0.94, 'The event is coherent and suitable.')
        );

        $this->assertTrue($applied);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame('approved', $request->fresh()->ai_decision);
        $this->assertTrue($event->fresh()->is_active);
        Mail::assertQueued(ApproveMail::class, 1);
        Mail::assertNotQueued(RejectMail::class);
    }

    public function test_ai_rejection_keeps_event_inactive_and_queues_existing_rejection_mail_with_reason(): void
    {
        [$event, $request] = $this->pendingRequest();
        $reason = 'The submission is unmistakable unrelated spam.';

        $this->service()->applyAiDecision(
            $request->id,
            $this->decision('rejected', 0.97, $reason)
        );

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('rejected', $request->ai_decision);
        $this->assertSame($reason, $request->ai_reason);
        $this->assertFalse($event->fresh()->is_active);
        Mail::assertQueued(
            RejectMail::class,
            fn (RejectMail $mail): bool => $mail->reason === $reason
        );
        Mail::assertNotQueued(ApproveMail::class);
    }

    public function test_manual_review_leaves_request_pending_and_emails_only_the_admin(): void
    {
        [$event, $request] = $this->pendingRequest();

        $this->service()->applyAiDecision(
            $request->id,
            $this->decision(
                'manual_review',
                0.62,
                'The historical claim cannot be verified from the supplied data.'
            )
        );

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame('manual_review', $request->ai_decision);
        $this->assertNotNull($request->ai_reviewed_at);
        $this->assertFalse($event->fresh()->is_active);
        Mail::assertQueued(
            EventNeedsManualReviewMail::class,
            fn (EventNeedsManualReviewMail $mail): bool => $mail->requestId === $request->id
                && $mail->eventId === $event->id
        );
        Mail::assertNotQueued(ApproveMail::class);
        Mail::assertNotQueued(RejectMail::class);
    }

    public function test_low_confidence_automatic_decision_is_converted_to_manual_review(): void
    {
        [$event, $request] = $this->pendingRequest();
        $decision = app(AiModerationResponseValidator::class)->validate([
            'decision' => 'approved',
            'confidence' => 0.70,
            'reason' => 'The submission appears acceptable.',
            'flags' => [],
        ]);

        $this->assertSame('manual_review', $decision->decision);
        $this->assertContains('below_auto_decision_threshold', $decision->flags);

        $this->service()->applyAiDecision($request->id, $decision);

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('manual_review', $request->fresh()->ai_decision);
        $this->assertFalse($event->fresh()->is_active);
        Mail::assertQueued(EventNeedsManualReviewMail::class, 1);
    }

    public function test_existing_admin_decision_wins_over_late_ai_result(): void
    {
        [$event, $request] = $this->pendingRequest();
        $request->update(['status' => 'rejected']);

        $applied = $this->service()->applyAiDecision(
            $request->id,
            $this->decision('approved', 0.99, 'Late result.')
        );

        $this->assertFalse($applied);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertNull($request->fresh()->ai_decision);
        $this->assertFalse($event->fresh()->is_active);
        Mail::assertNothingQueued();
    }

    public function test_manual_admin_approval_preserves_ai_review_history(): void
    {
        [$event, $request] = $this->pendingRequest();
        $reviewedAt = now()->subMinute();
        $request->update([
            'ai_decision' => 'manual_review',
            'ai_confidence' => 0.61,
            'ai_reason' => 'An administrator must verify the event claim.',
            'ai_raw_response' => ['flags' => ['uncertain_claim']],
            'ai_reviewed_at' => $reviewedAt,
            'ai_review_status' => 'completed',
        ]);

        $updated = $this->service()->approveManually($request->id);

        $this->assertSame('approved', $updated->status);
        $this->assertSame('manual_review', $updated->ai_decision);
        $this->assertSame('0.6100', $updated->ai_confidence);
        $this->assertSame('An administrator must verify the event claim.', $updated->ai_reason);
        $this->assertTrue($event->fresh()->is_active);
        Mail::assertQueued(ApproveMail::class, 1);
    }

    public function test_malformed_ai_response_never_changes_request_or_event_state(): void
    {
        [$event, $request] = $this->pendingRequest();
        config()->set('event_moderation.n8n.webhook_secret', str_repeat('s', 32));
        config()->set('event_moderation.n8n.webhook_url', 'https://n8n.test/webhook/scemory-event-moderation');
        Http::fake([
            'https://n8n.test/*' => Http::response([
                'decision' => 'publish_it',
                'confidence' => 1,
                'reason' => 'Unsupported decision.',
                'flags' => [],
            ]),
        ]);

        $job = new ReviewEventRequestWithAi($request->id);

        try {
            $job->handle(
                $this->service(),
                app(EventModerationPayloadBuilder::class),
                app(N8nEventModerationClient::class),
                app(AiModerationResponseValidator::class),
            );
            $this->fail('Malformed AI output should throw so the queue can retry safely.');
        } catch (RuntimeException) {
            // Expected: the queue retries and ultimately marks operational failure.
        }

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertNull($request->fresh()->ai_decision);
        $this->assertFalse($event->fresh()->is_active);
        Mail::assertNothingQueued();
    }

    public function test_n8n_outage_never_approves_or_rejects_the_request(): void
    {
        [$event, $request] = $this->pendingRequest();
        config()->set('event_moderation.n8n.webhook_secret', str_repeat('s', 32));
        config()->set('event_moderation.n8n.webhook_url', 'https://n8n.test/webhook/scemory-event-moderation');
        Http::fake([
            'https://n8n.test/*' => Http::response(['message' => 'Unavailable'], 503),
        ]);

        $job = new ReviewEventRequestWithAi($request->id);

        try {
            $job->handle(
                $this->service(),
                app(EventModerationPayloadBuilder::class),
                app(N8nEventModerationClient::class),
                app(AiModerationResponseValidator::class),
            );
            $this->fail('An n8n outage should throw so the queue can retry.');
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertNull($request->fresh()->ai_decision);
        $this->assertFalse($event->fresh()->is_active);
        Mail::assertNothingQueued();
    }

    public function test_duplicate_ai_result_does_not_send_duplicate_mail(): void
    {
        [, $request] = $this->pendingRequest();
        $decision = $this->decision('approved', 0.96, 'The event is acceptable.');

        $this->assertTrue($this->service()->applyAiDecision($request->id, $decision));
        $this->assertFalse($this->service()->applyAiDecision($request->id, $decision));

        Mail::assertQueued(ApproveMail::class, 1);
    }

    private function service(): EventRequestModerationService
    {
        return app(EventRequestModerationService::class);
    }

    /** @return array{0: Events, 1: EventRequestCreate} */
    private function pendingRequest(): array
    {
        $user = User::factory()->create([
            'name' => 'Event Owner',
            'email' => 'owner@example.com',
        ]);
        $event = Events::query()->create([
            'user_id' => $user->id,
            'title' => 'A coherent test event',
            'description' => 'A complete event description for moderation.',
            'is_active' => false,
            'is_real' => true,
            'is_historical' => false,
        ]);
        $request = EventRequestCreate::query()->create([
            'event_id' => $event->id,
            'status' => 'pending',
        ]);

        return [$event, $request];
    }

    /** @param array<int, string> $flags */
    private function decision(
        string $decision,
        float $confidence,
        string $reason,
        array $flags = []
    ): ModerationDecision {
        $raw = compact('decision', 'confidence', 'reason', 'flags');

        return new ModerationDecision(
            decision: $decision,
            confidence: $confidence,
            reason: $reason,
            flags: $flags,
            rawResponse: $raw,
            workflowExecutionId: 'execution-123',
        );
    }
}
