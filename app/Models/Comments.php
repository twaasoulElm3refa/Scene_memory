<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{
    protected $table = 'comments';

    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function translations()
    {
        return $this->hasMany(CommentTranslation::class, 'comment_id');
    }

    public function translation()
    {
        return $this->hasOne(CommentTranslation::class, 'comment_id')
            ->where('locale', app()->getLocale());
    }

    public function interactions()
    {
        return $this->hasMany(CommentInteractions::class, 'comment_id');
    }

    public function report()
    {
        return $this->hasMany(CommentReport::class,'comment_id');
    }

    public function replies()
    {
        return $this->hasMany(CommentReplies::class,'comment_id');
    }
}
