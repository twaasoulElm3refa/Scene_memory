<?php

namespace App\Observers;

use App\Models\Tags;
use App\Services\EventTagCacheService;

class TagCacheObserver
{
    public function __construct(private readonly EventTagCacheService $cache) {}

    public function created(Tags $tag): void
    {
        $this->cache->invalidateTagLists();
    }

    public function updated(Tags $tag): void
    {
        $this->cache->invalidate();
    }

    public function deleted(Tags $tag): void
    {
        $this->cache->invalidate();
    }

    public function restored(Tags $tag): void
    {
        $this->cache->invalidate();
    }
}
