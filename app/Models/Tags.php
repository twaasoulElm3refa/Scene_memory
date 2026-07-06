<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tags extends Model
{
    /** @use HasFactory<\Database\Factories\TagsFactory> */
    use HasFactory, SoftDeletes;
    protected $table='tags';
    protected $guarded = [];

    public function event_tag()
    {
        return $this->belongsTo(Event_Tags::class,'tag_id');
    }

    public function images()
    {
        return $this->belongsToMany(EventsImges::class, 'images_tags', 'tags_id', 'events_imges_id');
    }
}
