<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class WeeklyCompetitionPeriod
{
    public function timezone(): string
    {
        return config('competition.timezone', 'Asia/Riyadh');
    }

    public function currentWeekStart(): CarbonImmutable
    {
        return $this->weekStartFor(CarbonImmutable::now($this->timezone()));
    }

    // End is exclusive: next Saturday at local midnight, including across DST.
    public function currentWeekEnd(): CarbonImmutable
    {
        return $this->currentWeekStart()->addWeek();
    }

    public function weekStartFor(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone($this->timezone())
            ->startOfWeek(CarbonInterface::SATURDAY)->startOfDay();
    }

    public function weekEndFor(CarbonInterface $date): CarbonImmutable
    {
        return $this->weekStartFor($date)->addWeek();
    }

    public function previousWeekStart(): CarbonImmutable
    {
        return $this->currentWeekStart()->subWeek();
    }

    public function previousWeekEnd(): CarbonImmutable
    {
        return $this->currentWeekStart();
    }

    public function weekIdentifier(?CarbonInterface $date = null): string
    {
        return ($date ? $this->weekStartFor($date) : $this->currentWeekStart())->toDateString();
    }

    public function metadata(?CarbonInterface $date = null): array
    {
        $start = $date ? $this->weekStartFor($date) : $this->currentWeekStart();

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $start->addDays(6)->toDateString(),
            'timezone' => $this->timezone(),
            'ends_at' => $start->addWeek()->utc()->toIso8601String(),
        ];
    }
}
