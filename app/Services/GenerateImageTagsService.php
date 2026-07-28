<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class GenerateImageTagsService
{
    public function flagEventContent(
        string $title,
        ?string $description,
        ?int $eventId = null,
        ?int $eventRequestCreateId = null
    ): bool {
        Log::info('AI moderation request started', [
            'event_id' => $eventId,
            'event_request_create_id' => $eventRequestCreateId,
            'title_length' => mb_strlen((string) $title),
            'description_length' => mb_strlen((string) ($description ?? '')),
        ]);

        $payload = $this->buildModerationPayload($title, $description);
        $response = $this->sendModerationRequest($payload, $eventId);
        $responseBody = $this->moderationResponseBody($response);
        $this->logModerationResponseStructure($eventId, $response, $responseBody);

        if ($response->successful() && $this->shouldRetryModeration($responseBody)) {
            $finishReason = data_get($responseBody, 'choices.0.finish_reason');

            Log::warning('AI moderation retrying empty response', [
                'event_id' => $eventId,
                'first_finish_reason' => $finishReason,
            ]);

            $payload = $this->buildModerationPayload($title, $description, 512);
            $response = $this->sendModerationRequest($payload, $eventId);
            $responseBody = $this->moderationResponseBody($response);
            $this->logModerationResponseStructure($eventId, $response, $responseBody);
        }

        if ($response->failed()) {
            Log::error('Event content moderation: OpenRouter request failed', [
                'event_id' => $eventId,
                'status' => $response->status(),
                'provider_error_code' => $this->safeProviderErrorValue(
                    data_get($responseBody, 'error.code')
                ),
                'provider_error_message' => $this->safeProviderErrorValue(
                    data_get($responseBody, 'error.message')
                ),
                'model' => $this->model(),
            ]);

            return false;
        }

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

    public function handle(array $validated, ?Authenticatable $user = null): array
    {
        $images = collect($validated['images'] ?? [])
            ->take(max(0, (int) config('ai_tags.images_limit', 5)))
            ->map(fn (UploadedFile $image) => $this->uploadedImageToDataUrl($image))
            ->values()
            ->all();

        return $this->requestTags(
            title: (string) $validated['title'],
            description: $validated['description'] ?? null,
            imageDataUrls: $images,
            language: (string) ($validated['language'] ?? config('ai_tags.language', 'ar')),
            eventId: null
        );
    }

    /**
     * @param  array<int, string>  $storedPaths
     */
    public function handleStoredImages(
        string $title,
        ?string $description,
        array $storedPaths,
        string $language = 'ar',
        ?int $eventId = null
    ): array {
        $disk = Storage::disk('public');
        $imageDataUrls = [];

        foreach (array_slice($storedPaths, 0, max(0, (int) config('ai_tags.images_limit', 5))) as $path) {
            $path = ltrim(trim((string) $path), '/');

            if ($path === '' || ! $disk->exists($path)) {
                continue;
            }

            $contents = $disk->get($path);
            $mimeType = (string) $disk->mimeType($path);

            if ($contents === '' || ! str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $imageDataUrls[] = "data:{$mimeType};base64,".base64_encode($contents);
        }

        return $this->requestTags(
            title: $title,
            description: $description,
            imageDataUrls: $imageDataUrls,
            language: $language,
            eventId: $eventId
        );
    }

    /**
     * @param  array<int, string>  $imageDataUrls
     */
    private function requestTags(
        string $title,
        ?string $description,
        array $imageDataUrls,
        string $language,
        ?int $eventId = null
    ): array {
        try {
            $response = Http::withToken($this->apiKey())
                ->acceptJson()
                ->asJson()
                ->timeout(60)
                ->connectTimeout(10)
                ->post($this->endpoint(), $this->buildPayload([
                    'title' => $title,
                    'description' => $description,
                    'language' => $language,
                    'image_data_urls' => $imageDataUrls,
                ]));
        } catch (Throwable $exception) {
            Log::error('GenerateEventAiTagsJob: OpenRouter request failed', [
                'event_id' => $eventId,
                'status' => null,
                'provider_error_code' => $exception->getCode() !== 0
                    ? $this->safeProviderErrorValue($exception->getCode())
                    : null,
                'provider_error_message' => $this->safeProviderErrorValue($exception->getMessage()),
                'model' => $this->model(),
            ]);

            throw new RuntimeException(
                'Image tags provider request failed.',
                previous: $exception
            );
        }

        if ($response->failed()) {
            $providerResponse = $response->json();

            Log::error('GenerateEventAiTagsJob: OpenRouter request failed', [
                'event_id' => $eventId,
                'status' => $response->status(),
                'provider_error_code' => $this->safeProviderErrorValue(
                    data_get($providerResponse, 'error.code')
                ),
                'provider_error_message' => $this->safeProviderErrorValue(
                    data_get($providerResponse, 'error.message')
                ),
                'model' => $this->model(),
            ]);

            throw new RuntimeException('Image tags provider request failed.');
        }

        $responseBody = $response->json();
        $content = $this->extractContent(
            is_array($responseBody) ? $responseBody : []
        );

        if (blank($content)) {
            $exception = new RuntimeException('Empty response content from provider.');
            $this->logInvalidJsonResponse($eventId, $exception);

            throw $exception;
        }

        $decoded = $this->decodeJson($content, $eventId);

        return $this->normalizeResult(
            result: $decoded,
            imagesCount: count($imageDataUrls)
        );
    }

    protected function buildPayload(array $validated): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => $this->buildPrompt(
                    title: $validated['title'],
                    description: $validated['description'] ?? null,
                    imagesCount: count($validated['image_data_urls'] ?? []),
                    language: $validated['language'] ?? 'ar',
                ),
            ],
        ];

        foreach ($validated['image_data_urls'] ?? [] as $dataUrl) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $dataUrl,
                ],
            ];
        }

        return [
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 300,
        ];
    }

    protected function buildModerationPayload(
        string $title,
        ?string $description,
        int $maxCompletionTokens = 256
    ): array
    {
        return [
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $this->buildModerationPrompt($title, $description),
                        ],
                    ],
                ],
            ],
            'temperature' => 0,
            'max_completion_tokens' => $maxCompletionTokens,
            'reasoning' => [
                'enabled' => false,
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
        ];
    }

    protected function buildModerationPrompt(string $title, ?string $description): string
    {
        return <<<PROMPT
You are a strict content moderation classifier for event submissions.

Check the title and description for:
- profanity and obscene language
- insults, bullying, and degrading language
- sexually explicit content or sexual innuendo
- hate speech or discrimination
- threats, harassment, incitement, or calls for violence
- offensive words hidden with spaces, symbols, numbers, repeated characters, or separated letters

Cover Arabic and English, including Egyptian Arabic and common Arabic dialects.

Return valid JSON only, exactly one of:
{"flagged":true}
{"flagged":false}

Title:
{$title}

Description:
{$description}
PROMPT;
    }

    protected function buildPrompt(
        string $title,
        ?string $description,
        int $imagesCount,
        string $language
    ): string {
        $eventTagsLimit = $this->eventTagsLimit();
        $imageTagsLimit = $this->imageTagsLimit();

        return <<<PROMPT
Analyze the provided news content and image(s).

Language for tags: {$language}

Title:
{$title}

Description:
{$description}

Instructions:
1. Extract "event_tags" from the title and description only.
2. Extract "image_tags" from each image based only on visible content in that image.
3. Do not identify people by name unless absolutely certain.
4. Do not include hashtags.
5. Do not include explanations.
6. Return valid JSON only.
7. Keep event_tags concise and relevant.
8. Return no more than {$eventTagsLimit} event_tags.
9. Return no more than {$imageTagsLimit} tags for each image.
10. The number of image objects must be exactly {$imagesCount}.

Return exactly in this JSON structure:
{
  "event_tags": ["tag 1", "tag 2"],
  "images": [
    {
      "image_index": 1,
      "tags": ["tag 1", "tag 2"]
    }
  ]
}
PROMPT;
    }

    protected function extractContent(array $response): ?string
    {
        $content = data_get($response, 'choices.0.message.content');

        if (is_string($content)) {
            return $this->cleanJson($content);
        }

        if (is_array($content)) {
            $text = collect($content)
                ->pluck('text')
                ->filter(fn ($value) => is_string($value) && $value !== '')
                ->implode("\n");

            return $this->cleanJson($text);
        }

        return null;
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
        $eventTags = $this->normalizeTags(
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

                $tags = $this->normalizeTags(
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

    protected function cleanJson(string $content): string
    {
        $content = trim($content);

        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        return trim($content);
    }

    protected function uploadedImageToDataUrl(UploadedFile $image): string
    {
        $mimeType = $image->getMimeType() ?: 'image/jpeg';
        $path = $image->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new RuntimeException('Uploaded image could not be read.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Uploaded image could not be read.');
        }

        return "data:{$mimeType};base64,".base64_encode($contents);
    }

    protected function apiKey(): string
    {
        return (string) config('services.openrouter.api_key');
    }

    protected function model(): string
    {
        return (string) config('services.openrouter.model', 'openrouter/free');
    }

    protected function endpoint(): string
    {
        return rtrim((string) config('services.openrouter.api_url'), '/').'/chat/completions';
    }

    /**
     * @param  array<mixed>  $tags
     * @return array<string>
     */
    private function normalizeTags(array $tags, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $tag = preg_replace('/\s+/u', ' ', trim($tag));

            if ($tag === '') {
                continue;
            }

            $key = mb_strtolower($tag);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $tag;

            if (count($normalized) >= max(0, $limit)) {
                break;
            }
        }

        return $normalized;
    }

    private function eventTagsLimit(): int
    {
        return max(0, (int) config('ai_tags.event_tags_limit', 8));
    }

    private function imageTagsLimit(): int
    {
        return max(0, (int) config('ai_tags.image_tags_limit', 10));
    }

    private function logInvalidJsonResponse(?int $eventId, Throwable $exception): void
    {
        Log::error('GenerateEventAiTagsJob: invalid AI JSON response', [
            'event_id' => $eventId,
            'model' => $this->model(),
            'message' => $exception->getMessage(),
        ]);
    }

    private function logInvalidModerationResponse(?int $eventId, Throwable $exception): void
    {
        Log::error('Event content moderation: invalid AI JSON response', [
            'event_id' => $eventId,
            'model' => $this->model(),
            'message' => $exception->getMessage(),
        ]);
    }

    private function sendModerationRequest(array $payload, ?int $eventId)
    {
        Log::info('OpenRouter moderation request payload', [
            'event_id' => $eventId,
            'model' => $payload['model'] ?? null,
            'max_tokens' => $payload['max_tokens'] ?? null,
            'max_completion_tokens' => $payload['max_completion_tokens'] ?? null,
            'temperature' => $payload['temperature'] ?? null,
            'reasoning' => $payload['reasoning'] ?? null,
            'reasoning_effort' => $payload['reasoning_effort'] ?? null,
            'response_format' => $payload['response_format'] ?? null,
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        try {
            return Http::withToken($this->apiKey())
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->connectTimeout(10)
                ->post($this->endpoint(), $payload);
        } catch (Throwable $exception) {
            Log::error('Event content moderation: OpenRouter request failed', [
                'event_id' => $eventId,
                'status' => null,
                'provider_error_code' => $exception->getCode() !== 0
                    ? $this->safeProviderErrorValue($exception->getCode())
                    : null,
                'provider_error_message' => $this->safeProviderErrorValue($exception->getMessage()),
                'model' => $this->model(),
            ]);

            throw $exception;
        }
    }

    private function moderationResponseBody($response): array
    {
        $responseBody = $response->json();

        return is_array($responseBody) ? $responseBody : [];
    }

    private function logModerationResponseStructure(?int $eventId, $response, array $responseBody): void
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
            'content' => $this->safeModerationContent($rawContent),
            'extracted_content' => $this->safeProviderErrorValue($this->extractContent($responseBody)),
            'reasoning' => $this->safeModerationContent(data_get(
                $responseBody,
                'choices.0.message.reasoning'
            )),
            'reasoning_details' => $this->safeModerationContent(data_get(
                $responseBody,
                'choices.0.message.reasoning_details'
            )),
            'usage' => data_get($responseBody, 'usage'),
            'has_tool_calls' => ! empty(data_get($responseBody, 'choices.0.message.tool_calls')),
            'error' => data_get($responseBody, 'error'),
        ]);
    }

    private function shouldRetryModeration(array $responseBody): bool
    {
        $finishReason = data_get($responseBody, 'choices.0.finish_reason');
        $content = data_get($responseBody, 'choices.0.message.content');

        if ($finishReason === 'length') {
            return true;
        }

        return $content === null || trim((string) $content) === '';
    }

    private function safeModerationContent(mixed $content): mixed
    {
        if (is_string($content) || is_numeric($content)) {
            return $this->safeProviderErrorValue($content);
        }

        if ($content === null || is_bool($content)) {
            return $content;
        }

        if (is_array($content)) {
            $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false
                ? '[unencodable array]'
                : $this->safeProviderErrorValue($encoded);
        }

        return '['.get_debug_type($content).']';
    }

    private function safeProviderErrorValue(mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        if ($value === null || $value === '') {
            return null;
        }

        $apiKey = $this->apiKey();

        if ($apiKey !== '') {
            $value = str_replace($apiKey, '[redacted]', $value);
        }

        return Str::limit($value, 500, '…');
    }
}
