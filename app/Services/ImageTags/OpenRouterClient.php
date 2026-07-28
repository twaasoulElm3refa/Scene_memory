<?php

namespace App\Services\ImageTags;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OpenRouterClient
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly HttpFactory $http,
        private readonly OpenRouterLogSanitizer $sanitizer
    ) {}

    public function sendTagsRequest(array $payload, ?int $eventId = null): Response
    {
        try {
            $response = $this->http
                ->withToken($this->apiKey())
                ->acceptJson()
                ->asJson()
                ->timeout(60)
                ->connectTimeout(10)
                ->post($this->endpoint(), $payload);
        } catch (Throwable $exception) {
            Log::error('GenerateEventAiTagsJob: OpenRouter request failed', [
                'event_id' => $eventId,
                'status' => null,
                'provider_error_code' => $exception->getCode() !== 0
                    ? $this->sanitizer->providerErrorValue($exception->getCode())
                    : null,
                'provider_error_message' => $this->sanitizer->providerErrorValue($exception->getMessage()),
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
                'provider_error_code' => $this->sanitizer->providerErrorValue(
                    data_get($providerResponse, 'error.code')
                ),
                'provider_error_message' => $this->sanitizer->providerErrorValue(
                    data_get($providerResponse, 'error.message')
                ),
                'model' => $this->model(),
            ]);

            throw new RuntimeException('Image tags provider request failed.');
        }

        return $response;
    }

    public function sendModerationRequest(array $payload, ?int $eventId = null): Response
    {
        $this->logModerationPayload($payload, $eventId);

        try {
            return $this->http
                ->withToken($this->apiKey())
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
                    ? $this->sanitizer->providerErrorValue($exception->getCode())
                    : null,
                'provider_error_message' => $this->sanitizer->providerErrorValue($exception->getMessage()),
                'model' => $this->model(),
            ]);

            throw $exception;
        }
    }

    public function logModerationFailedResponse(Response $response, array $responseBody, ?int $eventId = null): void
    {
        Log::error('Event content moderation: OpenRouter request failed', [
            'event_id' => $eventId,
            'status' => $response->status(),
            'provider_error_code' => $this->sanitizer->providerErrorValue(
                data_get($responseBody, 'error.code')
            ),
            'provider_error_message' => $this->sanitizer->providerErrorValue(
                data_get($responseBody, 'error.message')
            ),
            'model' => $this->model(),
        ]);
    }

    private function logModerationPayload(array $payload, ?int $eventId): void
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
    }

    private function apiKey(): string
    {
        return (string) $this->config->get('services.openrouter.api_key');
    }

    private function model(): string
    {
        return (string) $this->config->get('services.openrouter.model', 'openrouter/free');
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->config->get('services.openrouter.api_url'), '/').'/chat/completions';
    }
}
