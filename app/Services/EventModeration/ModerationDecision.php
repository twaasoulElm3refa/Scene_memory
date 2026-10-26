<?php

namespace App\Services\EventModeration;

final readonly class ModerationDecision
{
    /**
     * @param  array<int, string>  $flags
     * @param  array<string, mixed>  $rawResponse
     * @param  array<int, string>  $policyCategories
     */
    public function __construct(
        public string $decision,
        public float $confidence,
        public string $reason,
        public array $flags,
        public array $rawResponse,
        public ?string $workflowExecutionId = null,
        public ?string $reasonCode = null,
        public array $policyCategories = [],
        public bool $banRecommended = false,
    ) {}
}
