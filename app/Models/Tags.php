<?php

namespace App\Models;

use Database\Factories\TagsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tags extends Model
{
    /** @use HasFactory<TagsFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'tags';

    protected $guarded = [];

    public function event_tag()
    {
        return $this->belongsTo(Event_Tags::class, 'tag_id');
    }

    public function events()
    {
        return $this->belongsToMany(
            Events::class,
            'event__tags',
            'tag_id',
            'event_id'
        )->wherePivotNull('deleted_at');
    }

    public function images()
    {
        return $this->belongsToMany(EventsImges::class, 'images_tags', 'tags_id', 'events_imges_id');
    }
}
