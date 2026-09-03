<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class LocationCacheService
{
    public function invalidate(): void
    {
        foreach (['countries', 'cities'] as $tag) {
            try {
                Cache::tags([$tag])->flush();
            } catch (Throwable $exception) {
                if (! str_contains($exception->getMessage(), 'does not support tagging')) {
                    Log::warning('location_cache_invalidation_failed', [
                        'tag' => $tag,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }
}
