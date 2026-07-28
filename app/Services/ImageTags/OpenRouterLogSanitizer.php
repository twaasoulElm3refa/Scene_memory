<?php

namespace App\Services\ImageTags;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

class OpenRouterLogSanitizer
{
    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    public function providerErrorValue(mixed $value): int|string|null
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

        $apiKey = (string) $this->config->get('services.openrouter.api_key');

        if ($apiKey !== '') {
            $value = str_replace($apiKey, '[redacted]', $value);
        }

        return Str::limit($value, 500, '…');
    }

    public function moderationContent(mixed $content): mixed
    {
        if (is_string($content) || is_numeric($content)) {
            return $this->providerErrorValue($content);
        }

        if ($content === null || is_bool($content)) {
            return $content;
        }

        if (is_array($content)) {
            $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false
                ? '[unencodable array]'
                : $this->providerErrorValue($encoded);
        }

        return '['.get_debug_type($content).']';
    }
}
