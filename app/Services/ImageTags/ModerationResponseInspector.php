<?php

namespace App\Services\ImageTags;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class ModerationResponseInspector
{
    public function __construct(
        private readonly ResponseContentExtractor $contentExtractor,
        private readonly OpenRouterLogSanitizer $sanitizer
    ) {}

    public function responseBody(Response $response): array
    {
        $responseBody = $response->json();

        return is_array($responseBody) ? $responseBody : [];
    }

    public function logStructure(?int $eventId, Response $response, array $responseBody): void
    {
        $rawContent = data_get($responseBody, 'choices.0.message.content');

        Log::info('OpenRouter moderation response structure', [
            'event_id' => $eventId,
            'model' => data_get($responseBody, 'model'),
            'provider' => data_get($responseBody, 'provider'),
            'status' => $response->status(),
            'successful' => $response->successful(),
            'top_level_keys' => array_keys($responseBody),
            'finish_reason' => data_get($responseBody, 'choices.0.finish_reason'),
            'content_type' => get_debug_type($rawContent),
            'content' => $this->sanitizer->moderationContent($rawContent),
            'extracted_content' => $this->sanitizer->providerErrorValue(
                $this->contentExtractor->extract($responseBody)
            ),
            'reasoning' => $this->sanitizer->moderationContent(data_get(
                $responseBody,
                'choices.0.message.reasoning'
            )),
            'reasoning_details' => $this->sanitizer->moderationContent(data_get(
                $responseBody,
                'choices.0.message.reasoning_details'
            )),
            'usage' => data_get($responseBody, 'usage'),
            'has_tool_calls' => ! empty(data_get($responseBody, 'choices.0.message.tool_calls')),
            'error' => data_get($responseBody, 'error'),
        ]);
    }

    public function shouldRetry(array $responseBody): bool
    {
        $finishReason = data_get($responseBody, 'choices.0.finish_reason');
        $content = data_get($responseBody, 'choices.0.message.content');

        if ($finishReason === 'length') {
            return true;
        }

        return $content === null || trim((string) $content) === '';
    }
}
