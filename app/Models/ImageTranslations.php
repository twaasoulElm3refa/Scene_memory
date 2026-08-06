<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageTranslations extends Model
{
    protected $table = 'image_translations';
    protected $guarded = [];

    public function image()
    {
        return $this->belongsTo(EventsImges::class, 'image_id');
    }

}
