<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDailyPoint extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'points' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
