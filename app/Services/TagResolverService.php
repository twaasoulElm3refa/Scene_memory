<?php

namespace App\Services;

use App\Models\Tags;
use Illuminate\Support\Str;

class TagResolverService
{
    public function resolve(?string $rawName): ?Tags
    {
        $name = $this->normalizeName($rawName);

        if ($name === null) {
            return null;
        }

        $slug = $this->slugFor($name);
        $tag = Tags::withTrashed()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name]
        );

        if ($tag->trashed()) {
            $tag->restore();
        }

        if (blank($tag->name)) {
            $tag->forceFill(['name' => $name])->save();
        }

        return $tag;
    }

    /**
     * @param  iterable<mixed>  $names
     * @return array<int>
     */
    public function resolveIds(iterable $names): array
    {
        $ids = [];
        $seen = [];

        foreach ($names as $rawName) {
            if (! is_string($rawName)) {
                continue;
            }

            $name = $this->normalizeName($rawName);

            if ($name === null) {
                continue;
            }

            $key = mb_strtolower($name);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $tag = $this->resolve($name);

            if ($tag !== null) {
                $ids[] = (int) $tag->getKey();
            }
        }

        return array_values(array_unique($ids));
    }

    public function normalizeName(?string $name): ?string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));

        return $name !== '' ? $name : null;
    }

    public function slugFor(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== ''
            ? $slug
            : 'tag-'.md5(mb_strtolower($name));
    }
}
