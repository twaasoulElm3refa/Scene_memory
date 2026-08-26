<?php

namespace App\Services\EventModeration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class N8nEventModerationClient
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly HttpFactory $http,
        private readonly WebhookSecretResolver $secretResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function review(array $payload): array
    {
        return $this->send($payload, 'moderation');
    }

    /** @param array<string, mixed> $payload */
    public function requestTranslation(array $payload): void
    {
        $response = $this->send(
            array_merge($payload, ['mode' => 'translation']),
            'translation'
        );

        if (($response['accepted'] ?? false) !== true) {
            throw new RuntimeException('n8n did not accept the event translation request.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload, string $operation): array
    {
        $secret = $this->secretResolver->resolve();

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Scemory-Webhook-Secret' => $secret,
                ])
                ->connectTimeout(10)
                ->timeout(max(10, (int) $this->config->get(
                    'event_moderation.n8n.webhook_timeout',
                    75
                )))
                ->post($this->webhookUrl(), $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                sprintf(
                    "n8n event {$operation} returned HTTP %d.",
                    $exception->response?->status() ?? 0
                ),
                previous: $exception
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                "n8n event {$operation} request failed.",
                previous: $exception
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException("n8n event {$operation} returned malformed JSON.");
        }

        return $body;
    }

    public function webhookUrl(): string
    {
        $configured = trim((string) $this->config->get(
            'event_moderation.n8n.webhook_url'
        ));

        if ($configured !== '') {
            return $configured;
        }

        $baseUrl = rtrim((string) $this->config->get(
            'event_moderation.n8n.base_url'
        ), '/');
        $path = trim((string) $this->config->get(
            'event_moderation.n8n.webhook_path',
            'scemory-event-moderation'
        ), '/');

        if ($baseUrl === '' || $path === '') {
            throw new RuntimeException('The n8n event moderation webhook URL is not configured.');
        }

        return "{$baseUrl}/webhook/{$path}";
    }
}
