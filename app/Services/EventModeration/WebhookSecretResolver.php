<?php

namespace App\Services\EventModeration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

class WebhookSecretResolver
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function resolve(): string
    {
        $configured = trim((string) $this->config->get(
            'event_moderation.n8n.webhook_secret'
        ));

        if ($configured !== '') {
            if (strlen($configured) < 32) {
                throw new RuntimeException(
                    'N8N_EVENT_MODERATION_WEBHOOK_SECRET must contain at least 32 characters.'
                );
            }

            return $configured;
        }

        $appKey = (string) $this->config->get('app.key');

        if ($appKey === '') {
            throw new RuntimeException(
                'APP_KEY or N8N_EVENT_MODERATION_WEBHOOK_SECRET is required.'
            );
        }

        return hash_hmac(
            'sha256',
            'scemory:n8n:event-moderation:webhook:v1',
            $appKey
        );
    }
}
