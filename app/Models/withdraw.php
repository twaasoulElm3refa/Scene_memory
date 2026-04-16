<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class withdraw extends Model
{
    protected $table = 'withdraws';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
