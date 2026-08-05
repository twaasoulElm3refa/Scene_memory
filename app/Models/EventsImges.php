<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventsImges extends Model
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

    public function cart()
    {
        return $this->belongsTo(Cart::class,'image_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tags::class, 'images_tags', 'events_imges_id', 'tags_id');
    }

    public function images_tags()
    {
        return $this->hasMany(ImagesTags::class, 'events_imges_id');
    }
}
