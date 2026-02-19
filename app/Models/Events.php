<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Events extends Model
{
    /** @use HasFactory<\Database\Factories\EventsFactory> */
    use HasFactory;

    protected $table = 'events';

    protected $guarded = [];

    public function city()
    {
        return $this->belongsTo(Cities::class, 'city_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event_tags()
    {
        return $this->hasMany(Event_Tags::class, 'event_id');
    }

    public function images()
    {
        return $this->hasMany(eventsImges::class, 'event_id');
    }

    public function sub_categorey()
    {
        return $this->belongsTo(subCategorey::class, 'sub_categorey_id');
    }

    public function requests()
    {
        return $this->hasMany(EventRequestCreate::class, 'event_id');
    }
}
