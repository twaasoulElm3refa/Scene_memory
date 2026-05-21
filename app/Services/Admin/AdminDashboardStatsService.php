<?php

namespace App\Services\Admin;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Purchases;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminDashboardStatsService
{
    public function getStats(): array
    {
        return Cache::remember('admin_dashboard_stats', now()->addMinutes(10), function () {
            return $this->buildStats();
        });
    }

    private function buildStats(): array
    {
        $totalEvents = $this->getEventStats();
        $activeUsers = $this->getActiveUsersStats();
        $totalMemories = $this->getMemoriesStats();
        $purchases = $this->getPurchasesStats();

        return [
            'total_events' => $totalEvents,
            'active_users' => $activeUsers,
            'total_memories' => $totalMemories,
            'purchases' => $purchases,
        ];
    }

    private function getEventStats(): array
    {
        $value = Events::query()->count();
        $currentMonth = $this->countCurrentMonth(Events::query());
        $previousMonth = $this->countPreviousMonth(Events::query());
        $change = $this->calculatePercentageChange($currentMonth, $previousMonth);

        return [
            'value' => $value,
            'percentage' => $change['percentage'],
            'trend' => $change['trend'],
            'label' => 'from last month',
        ];
    }

    private function getActiveUsersStats(): array
    {
        $baseQuery = User::query()->where('is_active', 1);

        $value = (clone $baseQuery)->count();
        $currentMonth = $this->countCurrentMonth(clone $baseQuery);
        $previousMonth = $this->countPreviousMonth(clone $baseQuery);
        $change = $this->calculatePercentageChange($currentMonth, $previousMonth);

        return [
            'value' => $value,
            'percentage' => $change['percentage'],
            'trend' => $change['trend'],
            'label' => 'from last month',
        ];
    }

    private function getMemoriesStats(): array
    {
        if (! class_exists(EventsImges::class)) {
            return [
                'value' => 0,
                'percentage' => 0,
                'trend' => 'neutral',
                'label' => 'from last month',
            ];
        }

        $table = (new EventsImges())->getTable();

        if (! Schema::hasTable($table)) {
            return [
                'value' => 0,
                'percentage' => 0,
                'trend' => 'neutral',
                'label' => 'from last month',
            ];
        }

        $baseQuery = EventsImges::query();
        $value = (clone $baseQuery)->count();

        if (! Schema::hasColumn($table, 'created_at')) {
            return [
                'value' => $value,
                'percentage' => 0,
                'trend' => 'neutral',
                'label' => 'from last month',
            ];
        }

        $currentMonth = $this->countCurrentMonth(clone $baseQuery);
        $previousMonth = $this->countPreviousMonth(clone $baseQuery);
        $change = $this->calculatePercentageChange($currentMonth, $previousMonth);

        return [
            'value' => $value,
            'percentage' => $change['percentage'],
            'trend' => $change['trend'],
            'label' => 'from last month',
        ];
    }

    private function getPurchasesStats(): array
    {
        $table = (new Purchases())->getTable();

        if (! Schema::hasTable($table)) {
            return [
                'value' => 0,
                'attention_count' => 0,
                'label' => 'items need attention',
            ];
        }

        $value = Purchases::query()->count();
        $attentionCount = 0;

        if (Schema::hasColumn($table, 'status')) {
            $healthyStatuses = ['paid', 'success', 'completed'];

            $attentionCount = Purchases::query()
                ->where(function ($query) use ($healthyStatuses) {
                    $query->whereNull('status')
                        ->orWhereRaw(
                            'LOWER(status) NOT IN (?, ?, ?)',
                            $healthyStatuses
                        );
                })
                ->count();
        }

        return [
            'value' => $value,
            'attention_count' => $attentionCount,
            'label' => 'items need attention',
        ];
    }

    private function countCurrentMonth($query): int
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        return (int) $query->whereBetween('created_at', [$start, $end])->count();
    }

    private function countPreviousMonth($query): int
    {
        $start = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $end = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        return (int) $query->whereBetween('created_at', [$start, $end])->count();
    }

    private function calculatePercentageChange($current, $previous): array
    {
        if ($previous == 0 && $current > 0) {
            return [
                'percentage' => 100.0,
                'trend' => 'up',
            ];
        }

        if ($previous == 0 && $current == 0) {
            return [
                'percentage' => 0.0,
                'trend' => 'neutral',
            ];
        }

        $percentage = (($current - $previous) / $previous) * 100;

        $trend = 'neutral';
        if ($percentage > 0) {
            $trend = 'up';
        } elseif ($percentage < 0) {
            $trend = 'down';
        }

        return [
            'percentage' => round($percentage, 1),
            'trend' => $trend,
        ];
    }
}
