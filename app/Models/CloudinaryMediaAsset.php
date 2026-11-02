<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudinaryMediaAsset extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'backup_verified_at' => 'datetime',
        'cleanup_completed_at' => 'datetime',
    ];

    public function eventMedia()
    {
        return $this->belongsTo(EventsImges::class, 'event_media_id');
    }
}
