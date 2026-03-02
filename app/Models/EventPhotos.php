<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPhotos extends Model
{
    protected $table ='event_photos';
    protected $guarded = [];

    public function Events()
    {
        return $this->hasMany(Events::class,'event_id');
    }
}
