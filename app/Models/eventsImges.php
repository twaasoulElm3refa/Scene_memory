<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class eventsImges extends Model
{
    protected $table = 'events_imges';

    protected $guarded =[];

    public function events()
    {
        return $this->belongsTo(Events::class,'event_id');
    }

    public function MediaRequest()
    {
        return $this->hasMany(MediaRequest::class, 'image_id');
    }
}
