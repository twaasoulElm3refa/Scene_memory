<?php

namespace App\Observers;

use App\Models\TagsTranslations;
use App\Services\EventTagCacheService;

class TagTranslationCacheObserver
{
    public function __construct(private readonly EventTagCacheService $cache) {}

    public function created(TagsTranslations $translation): void
    {
        $this->cache->invalidateTagLists();
    }

    public function updated(TagsTranslations $translation): void
    {
        $this->cache->invalidate();
    }

    public function deleted(TagsTranslations $translation): void
    {
        $this->cache->invalidate();
    }
}
