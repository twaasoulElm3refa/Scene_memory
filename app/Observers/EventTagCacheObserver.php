<?php

namespace App\Observers;

use App\Models\Event_Tags;
use App\Services\EventTagCacheService;

class EventTagCacheObserver
{
    public function __construct(private readonly EventTagCacheService $cache) {}

    public function created(Event_Tags $pivot): void
    {
        $this->cache->invalidate((int) $pivot->event_id);
    }

    public function updated(Event_Tags $pivot): void
    {
        $this->cache->invalidate((int) $pivot->event_id);
    }

    public function deleted(Event_Tags $pivot): void
    {
        $this->cache->invalidate((int) $pivot->event_id);
    }

    public function restored(Event_Tags $pivot): void
    {
        $this->cache->invalidate((int) $pivot->event_id);
    }
}
