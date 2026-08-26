<?php

namespace App\Jobs;

use App\Models\EventRequestCreate;
use App\Services\EventModeration\AiModerationResponseValidator;
use App\Services\EventModeration\EventModerationPayloadBuilder;
use App\Services\EventModeration\EventRequestModerationService;
use App\Services\EventModeration\N8nEventModerationClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewEventRequestWithAi implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 110;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $requestId,
        public readonly bool $translationOnly = false,
    ) {
        $this->onQueue((string) config('event_moderation.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return ($this->translationOnly ? 'translation:' : 'moderation:').$this->requestId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("event-request-ai-review:{$this->requestId}"))
                ->releaseAfter(30)
                ->expireAfter(max(120, (int) config(
                    'event_moderation.overlap_expire_after',
                    300
                ))),
        ];
    }

    public function handle(
        EventRequestModerationService $moderation,
        EventModerationPayloadBuilder $payloadBuilder,
        N8nEventModerationClient $n8n,
        AiModerationResponseValidator $validator,
    ): void {
        if ($this->translationOnly) {
            $this->requestTranslation($payloadBuilder, $n8n);

            return;
        }

        if (! (bool) config('event_moderation.enabled', true)) {
            Log::info('AI event moderation skipped because it is disabled', [
                'request_id' => $this->requestId,
            ]);

            return;
        }

        if (! $moderation->markProcessing($this->requestId)) {
            Log::info('AI event moderation skipped', [
                'request_id' => $this->requestId,
                'reason' => 'missing, already reviewed, or no longer pending',
            ]);

            return;
        }

        $request = EventRequestCreate::query()->find($this->requestId);

        if ($request === null) {
            return;
        }

        Log::info('AI event moderation started', [
            'request_id' => $request->id,
            'event_id' => $request->event_id,
            'attempt' => $request->ai_attempts,
        ]);

        try {
            $payload = $payloadBuilder->build($request);
            $response = $n8n->review($payload);
            $decision = $validator->validate($response);
            $applied = $moderation->applyAiDecision($this->requestId, $decision);

            if (! $applied) {
                Log::info('AI event moderation result ignored because a final decision already exists', [
                    'request_id' => $request->id,
                    'event_id' => $request->event_id,
                ]);

                return;
            }

            Log::info(
                $decision->decision === 'manual_review'
                    ? 'AI event moderation requires manual review'
                    : 'AI event moderation completed',
                [
                    'request_id' => $request->id,
                    'event_id' => $request->event_id,
                    'decision' => $decision->decision,
                    'confidence' => $decision->confidence,
                    'workflow_execution_id' => $decision->workflowExecutionId,
                ]
            );
        } catch (Throwable $exception) {
            Log::error('AI event moderation failed', [
                'request_id' => $request->id,
                'event_id' => $request->event_id,
                'attempt' => $this->attempts(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->translationOnly) {
            Log::error('n8n event translation request permanently failed', [
                'request_id' => $this->requestId,
                'job_id' => $this->job?->getJobId(),
                'attempts' => $this->attempts(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        app(EventRequestModerationService::class)->markFailed($this->requestId);

        Log::error('AI event moderation permanently failed', [
            'request_id' => $this->requestId,
            'job_id' => $this->job?->getJobId(),
            'attempts' => $this->attempts(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private function requestTranslation(
        EventModerationPayloadBuilder $payloadBuilder,
        N8nEventModerationClient $n8n,
    ): void {
        $request = EventRequestCreate::query()->find($this->requestId);

        if ($request === null || $request->status !== 'approved') {
            Log::info('n8n event translation request skipped', [
                'request_id' => $this->requestId,
                'reason' => 'missing or not approved',
            ]);

            return;
        }

        try {
            $payload = $payloadBuilder->build($request);
            $n8n->requestTranslation($payload);

            Log::info('n8n event translation request accepted', [
                'request_id' => $request->id,
                'event_id' => $request->event_id,
            ]);
        } catch (Throwable $exception) {
            Log::error('n8n event translation request failed', [
                'request_id' => $request->id,
                'event_id' => $request->event_id,
                'attempt' => $this->attempts(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
