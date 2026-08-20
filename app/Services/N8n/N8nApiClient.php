<?php

namespace App\Services\N8n;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Throwable;

class N8nApiClient
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly HttpFactory $http,
    ) {}

    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        return $this->request('GET', '/workflows', query: ['limit' => 1])->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workflows(): array
    {
        return $this->paginated('/workflows');
    }

    /** @return array<string, mixed> */
    public function workflow(string $id): array
    {
        return $this->request('GET', '/workflows/'.rawurlencode($id))->json();
    }

    /** @param array<string, mixed> $workflow */
    public function createWorkflow(array $workflow): array
    {
        return $this->request('POST', '/workflows', $workflow)->json();
    }

    /** @param array<string, mixed> $workflow */
    public function updateWorkflow(string $id, array $workflow): array
    {
        return $this->request(
            'PUT',
            '/workflows/'.rawurlencode($id),
            $workflow
        )->json();
    }

    /** @return array<string, mixed> */
    public function activateWorkflow(string $id): array
    {
        try {
            return $this->request(
                'POST',
                '/workflows/'.rawurlencode($id).'/activate'
            )->json();
        } catch (N8nApiException $exception) {
            if (! in_array($exception->status, [404, 405, 410], true)) {
                throw $exception;
            }

            return $this->request(
                'POST',
                '/workflows/'.rawurlencode($id).'/publish'
            )->json();
        }
    }

    /** @return array<string, mixed> */
    public function credentialSchema(string $type): array
    {
        return $this->request(
            'GET',
            '/credentials/schema/'.rawurlencode($type)
        )->json();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function credentialsIfSupported(): ?array
    {
        try {
            return $this->paginated('/credentials');
        } catch (N8nApiException $exception) {
            if (in_array($exception->status, [404, 405, 410], true)) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function createCredential(string $name, string $type, array $data): array
    {
        return $this->request('POST', '/credentials', [
            'name' => $name,
            'type' => $type,
            'data' => $data,
        ])->json();
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function updateCredential(
        string $id,
        string $name,
        string $type,
        array $data
    ): array {
        return $this->request(
            'PATCH',
            '/credentials/'.rawurlencode($id),
            [
                'name' => $name,
                'type' => $type,
                'data' => $data,
            ]
        )->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paginated(string $path): array
    {
        $items = [];
        $cursor = null;

        do {
            $query = ['limit' => 100];

            if (is_string($cursor) && $cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $body = $this->request('GET', $path, query: $query)->json();
            $pageItems = $body['data'] ?? [];

            if (! is_array($pageItems)) {
                throw new N8nApiException("n8n returned an invalid {$path} list.");
            }

            foreach ($pageItems as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            $cursor = $body['nextCursor'] ?? null;
        } while (is_string($cursor) && $cursor !== '');

        return $items;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    private function request(
        string $method,
        string $path,
        array $body = [],
        array $query = []
    ): Response {
        $apiKey = trim((string) $this->config->get('event_moderation.n8n.api_key'));

        if ($apiKey === '') {
            throw new N8nApiException('The n8n API key is not configured.');
        }

        try {
            $pending = $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders(['X-N8N-API-KEY' => $apiKey])
                ->connectTimeout(10)
                ->timeout(max(10, (int) $this->config->get(
                    'event_moderation.n8n.api_timeout',
                    30
                )));

            $url = $this->apiUrl().$path;

            if ($query !== []) {
                $pending = $pending->withQueryParameters($query);
            }

            $response = $pending->send($method, $url, $body === [] ? [] : [
                'json' => $body,
            ]);
        } catch (Throwable $exception) {
            throw new N8nApiException(
                'Unable to connect to the n8n API.',
                previous: $exception
            );
        }

        if ($response->failed()) {
            $message = $response->json('message')
                ?? $response->json('error')
                ?? 'Unexpected n8n API response.';

            if (! is_string($message)) {
                $message = 'Unexpected n8n API response.';
            }

            throw new N8nApiException(
                sprintf('n8n API HTTP %d: %s', $response->status(), mb_substr($message, 0, 500)),
                $response->status()
            );
        }

        if (! is_array($response->json())) {
            throw new N8nApiException(
                'n8n API returned malformed JSON.',
                $response->status()
            );
        }

        return $response;
    }

    private function apiUrl(): string
    {
        $baseUrl = rtrim((string) $this->config->get(
            'event_moderation.n8n.base_url'
        ), '/');

        if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new N8nApiException('The n8n base URL is invalid or missing.');
        }

        return str_ends_with($baseUrl, '/api/v1')
            ? $baseUrl
            : $baseUrl.'/api/v1';
    }
}
