<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagsTranslations extends Model
{
    protected $table = 'tags_translations';
    protected $guarded = [];

    public function tag()
    {
        return $this->belongsTo(Tags::class,'tag_id');
    }
}
