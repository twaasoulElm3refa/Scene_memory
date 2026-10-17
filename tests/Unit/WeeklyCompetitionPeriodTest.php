<?php

namespace Tests\Unit;

use App\Services\WeeklyCompetitionPeriod;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class WeeklyCompetitionPeriodTest extends TestCase
{
    public function test_sqlite_tests_cannot_inherit_the_compose_database_filename(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    public function test_friday_and_saturday_use_business_midnight_with_an_exclusive_end(): void
    {
        $period = app(WeeklyCompetitionPeriod::class);
        $friday = CarbonImmutable::parse('2026-10-23 23:59:59.999999', 'Asia/Riyadh');
        $saturday = CarbonImmutable::parse('2026-10-24 00:00:00', 'Asia/Riyadh');

        $this->assertSame('2026-10-17', $period->weekIdentifier($friday));
        $this->assertSame('2026-10-24', $period->weekIdentifier($saturday));
        $this->assertTrue($period->weekEndFor($friday)->equalTo($saturday));
        $this->assertSame('2026-10-24', $period->weekIdentifier($saturday->utc()));
        $this->assertSame('2026-10-23', $period->metadata($friday)['week_end']);
    }

    public function test_cross_year_weeks_use_the_saturday_date_as_identifier(): void
    {
        $period = app(WeeklyCompetitionPeriod::class);
        $date = CarbonImmutable::parse('2027-01-01 23:59:59', 'Asia/Riyadh');

        $this->assertSame('2026-12-26', $period->weekIdentifier($date));
        $this->assertSame('2027-01-02', $period->weekEndFor($date)->toDateString());
        $this->assertSame('2027-01-01', $period->metadata($date)['week_end']);
    }

    public function test_dst_changes_keep_local_midnight_boundaries(): void
    {
        config(['competition.timezone' => 'America/New_York']);
        $period = app(WeeklyCompetitionPeriod::class);
        foreach (['2026-03-08' => 167, '2026-11-01' => 169] as $date => $hours) {
            $date = CarbonImmutable::parse($date, $period->timezone());
            $start = $period->weekStartFor($date);
            $end = $period->weekEndFor($date);
            $this->assertSame('00:00:00', $start->format('H:i:s'));
            $this->assertSame('00:00:00', $end->format('H:i:s'));
            $this->assertSame($hours, (int) $start->diffInHours($end));
            $this->assertSame(6, $start->dayOfWeek);
            $this->assertSame(6, $end->dayOfWeek);
        }
    }
}
