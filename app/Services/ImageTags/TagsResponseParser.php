<?php

namespace App\Services\ImageTags;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

class TagsResponseParser
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly ResponseContentExtractor $contentExtractor,
        private readonly TagNormalizer $tagNormalizer
    ) {}

    public function parse(array $responseBody, int $imagesCount, ?int $eventId = null): array
    {
        $content = $this->contentExtractor->extract($responseBody);

        if (blank($content)) {
            $exception = new RuntimeException('Empty response content from provider.');
            $this->logInvalidJsonResponse($eventId, $exception);

            throw $exception;
        }

        $decoded = $this->decodeJson($content, $eventId);

        return $this->normalizeResult($decoded, $imagesCount);
    }

    protected function decodeJson(string $content, ?int $eventId = null): array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $exception = new RuntimeException('Invalid JSON response from provider.', previous: $e);
            $this->logInvalidJsonResponse($eventId, $e);

            throw $exception;
        }

        if (! is_array($decoded)) {
            $exception = new RuntimeException('Decoded content is not a valid array.');
            $this->logInvalidJsonResponse($eventId, $exception);

            throw $exception;
        }

        return $decoded;
    }

    protected function normalizeResult(array $result, int $imagesCount): array
    {
        $eventTags = $this->tagNormalizer->normalize(
            is_array($result['event_tags'] ?? null) ? $result['event_tags'] : [],
            $this->eventTagsLimit()
        );

        $images = collect(is_array($result['images'] ?? null) ? $result['images'] : [])
            ->filter(fn ($image) => is_array($image))
            ->map(function (array $image) use ($imagesCount) {
                $imageIndex = filter_var(
                    $image['image_index'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => max(1, $imagesCount)]]
                );

                if ($imageIndex === false || $imageIndex > $imagesCount) {
                    return null;
                }

                $tags = $this->tagNormalizer->normalize(
                    is_array($image['tags'] ?? null) ? $image['tags'] : [],
                    $this->imageTagsLimit()
                );

                return [
                    'image_index' => $imageIndex,
                    'tags' => $tags,
                ];
            })
            ->filter()
            ->unique('image_index')
            ->values();

        return [
            'event_tags' => $eventTags,
            'images' => $images->all(),
        ];
    }

    private function eventTagsLimit(): int
    {
        return max(0, (int) $this->config->get('ai_tags.event_tags_limit', 8));
    }

    private function imageTagsLimit(): int
    {
        return max(0, (int) $this->config->get('ai_tags.image_tags_limit', 10));
    }

    private function logInvalidJsonResponse(?int $eventId, Throwable $exception): void
    {
        Log::error('GenerateEventAiTagsJob: invalid AI JSON response', [
            'event_id' => $eventId,
            'model' => (string) $this->config->get('services.openrouter.model', 'openrouter/free'),
            'message' => $exception->getMessage(),
        ]);
    }
}
