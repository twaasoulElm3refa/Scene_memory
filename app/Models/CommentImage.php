<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CommentImage extends Model
{
    protected $guarded = [];

    protected $appends = ['url'];

    protected $hidden = [
        'comment_id',
        'disk',
        'original_name',
        'mime_type',
        'size',
        'created_at',
        'updated_at',
    ];

    public function comment()
    {
        return $this->belongsTo(Comments::class, 'comment_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }
}
