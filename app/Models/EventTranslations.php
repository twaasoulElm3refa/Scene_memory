<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTranslations extends Model
{
    protected $table = "event_translations";
    protected $guarded=[];

    public function events()
    {
        return $this->hasMany(Events::class,'event_id');
    }
}
