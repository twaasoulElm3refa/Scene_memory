<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventViews extends Model
{
    protected $table = 'event_views';

    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }
}
