<?php

namespace App\Services\EventModeration;

final readonly class ModerationDecision
{
    /**
     * @param  array<int, string>  $flags
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $decision,
        public float $confidence,
        public string $reason,
        public array $flags,
        public array $rawResponse,
        public ?string $workflowExecutionId = null,
    ) {}
}
