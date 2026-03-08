<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentReport extends Model
{
    protected $table= 'comment_reports';
    protected $guarded = [];

    public function comment()
    {
        return $this->belongsTo(comments::class,'comment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
