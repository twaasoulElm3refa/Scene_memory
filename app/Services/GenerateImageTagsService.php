<?php

namespace App\Services;

use App\Services\ImageTags\ImageDataUrlFactory;
use App\Services\ImageTags\ModerationResponseInspector;
use App\Services\ImageTags\ModerationResponseParser;
use App\Services\ImageTags\OpenRouterClient;
use App\Services\ImageTags\OpenRouterPayloadBuilder;
use App\Services\ImageTags\TagsResponseParser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class GenerateImageTagsService
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly ImageDataUrlFactory $imageDataUrlFactory,
        private readonly OpenRouterPayloadBuilder $payloadBuilder,
        private readonly OpenRouterClient $openRouterClient,
        private readonly TagsResponseParser $tagsResponseParser,
        private readonly ModerationResponseInspector $moderationInspector,
        private readonly ModerationResponseParser $moderationResponseParser
    ) {}

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

        $payload = $this->payloadBuilder->buildModerationPayload($title, $description);
        $response = $this->openRouterClient->sendModerationRequest($payload, $eventId);
        $responseBody = $this->moderationInspector->responseBody($response);
        $this->moderationInspector->logStructure($eventId, $response, $responseBody);

        if ($response->successful() && $this->moderationInspector->shouldRetry($responseBody)) {
            $finishReason = data_get($responseBody, 'choices.0.finish_reason');

            Log::warning('AI moderation retrying empty response', [
                'event_id' => $eventId,
                'first_finish_reason' => $finishReason,
            ]);

            $payload = $this->payloadBuilder->buildModerationPayload($title, $description, 512);
            $response = $this->openRouterClient->sendModerationRequest($payload, $eventId);
            $responseBody = $this->moderationInspector->responseBody($response);
            $this->moderationInspector->logStructure($eventId, $response, $responseBody);
        }

        if ($response->failed()) {
            $this->openRouterClient->logModerationFailedResponse($response, $responseBody, $eventId);

            return false;
        }

        return $this->moderationResponseParser->parse(
            $responseBody,
            $eventId,
            $eventRequestCreateId
        );
    }

    public function handle(array $validated, ?Authenticatable $user = null): array
    {
        $imageDataUrls = $this->imageDataUrlFactory->fromUploadedImages($validated['images'] ?? []);

        return $this->requestTags(
            title: (string) $validated['title'],
            description: $validated['description'] ?? null,
            imageDataUrls: $imageDataUrls,
            language: (string) ($validated['language'] ?? $this->config->get('ai_tags.language', 'ar')),
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
        return $this->requestTags(
            title: $title,
            description: $description,
            imageDataUrls: $this->imageDataUrlFactory->fromStoredPaths($storedPaths),
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
        $response = $this->openRouterClient->sendTagsRequest(
            $this->payloadBuilder->buildTagsPayload([
                'title' => $title,
                'description' => $description,
                'language' => $language,
                'image_data_urls' => $imageDataUrls,
            ]),
            $eventId
        );

        $responseBody = $response->json();
        $this->logTagsRawResponse(
            $response,
            is_array($responseBody) ? $responseBody : [],
            $eventId
        );

        return $this->tagsResponseParser->parse(
            is_array($responseBody) ? $responseBody : [],
            count($imageDataUrls),
            $eventId
        );
    }

    private function logTagsRawResponse(Response $response, array $responseBody, ?int $eventId): void
    {
        $content = data_get($responseBody, 'choices.0.message.content');

        Log::info('OpenRouter tags raw response', [
            'event_id' => $eventId,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'finish_reason' => data_get($responseBody, 'choices.0.finish_reason'),
            'content_type' => get_debug_type($content),
            'content' => is_string($content)
                ? mb_substr($content, 0, 2000)
                : $content,
            'body' => mb_substr($response->body(), 0, 2000),
            'model' => data_get($responseBody, 'model', $this->config->get('services.openrouter.model')),
            'error' => data_get($responseBody, 'error'),
            'has_provider_error' => data_get($responseBody, 'error') !== null,
            'has_tool_calls' => ! empty(data_get($responseBody, 'choices.0.message.tool_calls')),
            'has_reasoning' => data_get($responseBody, 'choices.0.message.reasoning') !== null
                || data_get($responseBody, 'choices.0.message.reasoning_details') !== null,
            'reasoning_type' => get_debug_type(data_get($responseBody, 'choices.0.message.reasoning')),
            'tool_calls_type' => get_debug_type(data_get($responseBody, 'choices.0.message.tool_calls')),
        ]);
    }
}
