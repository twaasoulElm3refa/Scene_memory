<?php

namespace App\Observers;

use App\Models\Events;
use App\Models\EventsImges;
use App\Services\Admin\AdminDashboardStatsService;
use App\Services\EventTagCacheService;

class AdminDashboardStatsObserver
{
    public function created($model): void
    {
        $this->clearStatsCache();
    }

    public function updated($model): void
    {
        $this->clearStatsCache();
        if ($model instanceof Events && $model->wasChanged(['is_active', 'slug'])) {
            app(EventTagCacheService::class)->invalidateEvent((int) $model->id);
        }
        if ($model instanceof EventsImges && $model->wasChanged(['is_active', 'price', 'original_disk', 'preview_disk', 'full_url', 'preview_url'])) {
            app(EventTagCacheService::class)->invalidateEvent((int) $model->event_id, [(int) $model->id]);
        }
    }

    public function deleted($model): void
    {
        $this->clearStatsCache();
    }

    private function clearStatsCache(): void
    {
        app(AdminDashboardStatsService::class)->clearStatsCache();
    }
}
