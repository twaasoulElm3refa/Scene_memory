<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'status' => 'boolean',
        ];
    }
}
