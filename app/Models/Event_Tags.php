<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event_Tags extends Model
{
    /** @use HasFactory<\Database\Factories\EventTagsFactory> */
    use HasFactory, SoftDeletes;

    protected $table='event__tags';
    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(Events::class,'event_id');
    }

    public function tag()
    {
        return $this->belongsTo(Tags::class,'tag_id');
    }
}
