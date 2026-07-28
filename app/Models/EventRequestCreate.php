<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRequestCreate extends Model
{
    protected $table = "event_request_creates";
    protected $guarded=[];

    protected $casts = [
        'ai_flagged' => 'boolean',
    ];

    public function events()
    {
        return $this->belongsTo(Events::class,'event_id');
    }
}
