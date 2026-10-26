<?php

namespace App\Support\Cache;

use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;

/**
 * A cache repository whose tags() never throws.
 *
 * Tagged caching needs a taggable store (Redis, Memcached, array). On the file or
 * database store Laravel throws, which turned every tagged page into an HTTP 500.
 * Here tagged reads bypass the cache instead: the value is computed each time and
 * tagged writes and flushes do nothing, so no stale data can be served. One
 * warning per store and process makes the misconfiguration visible.
 */
class TagSafeRepository extends Repository
{
    /** @var array<string, true> */
    private static array $warned = [];

    public function tags($names)
    {
        $names = is_array($names) ? $names : func_get_args();
        if ($this->supportsTags()) {
            return parent::tags($names);
        }

        $store = (string) ($this->getName() ?? get_class($this->getStore()));
        if (! isset(self::$warned[$store])) {
            self::$warned[$store] = true;
            Log::warning('cache_tags_unsupported', [
                'store' => $store,
                'effect' => 'Tagged cache reads are computed uncached until CACHE_STORE is redis.',
            ]);
        }

        return (new Repository(new NullStore))->tags($names);
    }
}
