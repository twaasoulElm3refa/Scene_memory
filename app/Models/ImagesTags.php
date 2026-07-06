<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagesTags extends Model
{
      protected $table = 'images_tags';
    protected $guarded = [];
    public function events_imges()
    {
        return $this->belongsTo(EventsImges::class,'events_imges_id');
    }

    public function tags()
    {
        return $this->belongsTo(Tags::class,'tags_id');
    }
}
