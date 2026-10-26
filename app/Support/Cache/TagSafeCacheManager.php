<?php

namespace App\Support\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Arr;

/** Builds every cache store with {@see TagSafeRepository}. */
class TagSafeCacheManager extends CacheManager
{
    public function repository(Store $store, array $config = [])
    {
        return tap(new TagSafeRepository($store, Arr::only($config, ['store'])), function ($repository) use ($config) {
            if ($config['events'] ?? true) {
                $this->setEventDispatcher($repository);
            }
        });
    }
}
