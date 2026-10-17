<?php

namespace App\Models;

class WeeklyLeaderboard extends UserWeeklyPoint
{
    protected function casts(): array
    {
        return ['points' => 'integer', 'rank' => 'integer'];
    }
}
