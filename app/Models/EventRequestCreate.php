<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRequestCreate extends Model
{
    protected $table = "event_request_creates";
    protected $guarded=[];

    public function events()
    {
        return $this->belongsTo(Events::class);
    }
}
