<?php

namespace App\Services\EventModeration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

class AiModerationResponseValidator
{
    private const ALLOWED_DECISIONS = [
        'approved',
        'rejected',
        'manual_review',
    ];

    public function __construct(private readonly ConfigRepository $config) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public function validate(array $response): ModerationDecision
    {
        $payload = $this->decisionPayload($response);
        $decision = strtolower(trim((string) ($payload['decision'] ?? '')));

        if (! in_array($decision, self::ALLOWED_DECISIONS, true)) {
            throw new RuntimeException('Invalid AI moderation decision.');
        }

        $confidence = $payload['confidence'] ?? null;

        if (! is_numeric($confidence)) {
            throw new RuntimeException('Invalid AI moderation confidence.');
        }

        $confidence = (float) $confidence;

        if (! is_finite($confidence) || $confidence < 0 || $confidence > 1) {
            throw new RuntimeException('AI moderation confidence must be between 0 and 1.');
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '' || mb_strlen($reason) > 5000) {
            throw new RuntimeException('AI moderation reason is missing or too long.');
        }

        $flags = $payload['flags'] ?? [];

        if (! is_array($flags)) {
            throw new RuntimeException('AI moderation flags must be an array.');
        }

        $flags = collect($flags)
            ->filter(fn (mixed $flag): bool => is_string($flag))
            ->map(fn (string $flag): string => trim($flag))
            ->filter()
            ->take(25)
            ->values()
            ->all();

        $threshold = min(1, max(0, (float) $this->config->get(
            'event_moderation.auto_decision_threshold',
            0.85
        )));

        if ($decision !== 'manual_review' && $confidence < $threshold) {
            $flags[] = 'below_auto_decision_threshold';
            $flags = array_values(array_unique($flags));
            $reason = sprintf(
                'AI confidence was below the %.2f automatic-decision threshold. %s',
                $threshold,
                $reason
            );
            $decision = 'manual_review';
        }

        return new ModerationDecision(
            decision: $decision,
            confidence: $confidence,
            reason: $reason,
            flags: $flags,
            rawResponse: $response,
            workflowExecutionId: $this->workflowExecutionId($response, $payload),
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function decisionPayload(array $response): array
    {
        $payload = data_get($response, 'data');

        if (is_array($payload) && array_key_exists('decision', $payload)) {
            return $payload;
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $payload
     */
    private function workflowExecutionId(array $response, array $payload): ?string
    {
        $id = $payload['workflow_execution_id']
            ?? $response['workflow_execution_id']
            ?? null;

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $id = trim((string) $id);

        return $id !== '' ? mb_substr($id, 0, 255) : null;
    }
}
