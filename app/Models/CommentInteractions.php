<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentInteractions extends Model
{
    protected $table = "comment_interactions";
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->belongsTo(Comments::class,'comment_id');
    }
}
