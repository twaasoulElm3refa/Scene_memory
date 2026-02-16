<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tags extends Model
{
    /** @use HasFactory<\Database\Factories\TagsFactory> */
    use HasFactory;
    protected $table='tags';
    protected $guarded = [];

    public function event_tag()
    {
        return $this->belongsTo(Event_Tags::class,'tag_id');
    }
}
