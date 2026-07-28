<?php

namespace App\Services\ImageTags;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class ModerationResponseParser
{
    public function parse(array $responseBody, ?int $eventId = null, ?int $eventRequestCreateId = null): bool
    {
        $content = data_get($responseBody, 'choices.0.message.content');
        $extractedContent = trim((string) $content);
        $extractedContent = preg_replace(
            '/^```(?:json)?\s*|\s*```$/i',
            '',
            $extractedContent
        );
        $extractedContent = trim((string) $extractedContent);

        if (blank($extractedContent)) {
            throw new RuntimeException('Empty response content from provider.');
        }

        $decoded = json_decode(
            $extractedContent,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            ! is_array($decoded) ||
            ! array_key_exists('flagged', $decoded) ||
            ! is_bool($decoded['flagged'])
        ) {
            throw new RuntimeException(
                'Invalid moderation response: flagged must be a boolean.'
            );
        }

        $flagged = $decoded['flagged'];

        Log::info('AI moderation parsed successfully', [
            'event_id' => $eventId,
            'event_request_create_id' => $eventRequestCreateId,
            'decoded' => $decoded,
            'flagged' => $flagged,
            'flagged_type' => get_debug_type($flagged),
        ]);

        return $flagged;
    }
}
