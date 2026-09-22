<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyLeaderboard extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'points' => 'integer',
            'rank' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
