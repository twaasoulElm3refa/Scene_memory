<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentReplies extends Model
{
    protected $table = 'comment_replies';
    protected $guarded = [];

    public function comment()
    {
        return $this->belongsTo(comments::class,'comment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
