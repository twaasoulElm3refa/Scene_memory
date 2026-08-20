<?php

namespace App\Observers;

use App\Models\ImagesTags;
use App\Services\EventTagCacheService;

class ImageTagCacheObserver
{
    public function __construct(private readonly EventTagCacheService $cache) {}

    public function created(ImagesTags $pivot): void
    {
        $this->invalidate($pivot);
    }

    public function updated(ImagesTags $pivot): void
    {
        $this->invalidate($pivot);
    }

    public function deleted(ImagesTags $pivot): void
    {
        $this->invalidate($pivot);
    }

    private function invalidate(ImagesTags $pivot): void
    {
        $media = $pivot->events_imges()->first(['id', 'event_id']);
        $this->cache->invalidate(
            $media?->event_id ? (int) $media->event_id : null,
            $media?->id ? [(int) $media->id] : []
        );
    }
}
