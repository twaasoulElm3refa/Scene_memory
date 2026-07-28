<?php

namespace App\Services\ImageTags;

class ResponseContentExtractor
{
    public function extract(array $response): ?string
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

    public function cleanJson(string $content): string
    {
        $content = trim($content);

        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        return trim($content);
    }
}
