<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPointHistory extends Model
{
    protected $table = 'user_points_history';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
