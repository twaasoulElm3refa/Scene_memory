<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentTranslation extends Model
{
    protected $table = 'comment_translations';

    protected $guarded = [];

    public function comment()
    {
        return $this->belongsTo(Comments::class, 'comment_id');
    }
}
