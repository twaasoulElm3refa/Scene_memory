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
        $content = trim((string) $content);

        return $this->extractJsonObject($content) ?? $content;
    }

    private function extractJsonObject(string $content): ?string
    {
        $start = mb_strpos($content, '{');

        if ($start === false) {
            return null;
        }

        $length = mb_strlen($content);
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($index = $start; $index < $length; $index++) {
            $char = mb_substr($content, $index, 1);

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return mb_substr($content, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }
}
