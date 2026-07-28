<?php

namespace App\Services\ImageTags;

class TagNormalizer
{
    /**
     * @param  array<mixed>  $tags
     * @return array<string>
     */
    public function normalize(array $tags, int $limit): array
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
}
