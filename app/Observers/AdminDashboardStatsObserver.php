<?php

namespace App\Observers;

use App\Services\Admin\AdminDashboardStatsService;

class AdminDashboardStatsObserver
{
    public function created($model): void
    {
        $this->clearStatsCache();
    }

    public function updated($model): void
    {
        $this->clearStatsCache();
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
