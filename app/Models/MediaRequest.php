<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaRequest extends Model
{
    protected $table = "media_requests";
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function image()
    {
        return $this->belongsTo(EventsImges::class,'image_id');
    }
}
