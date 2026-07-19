<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entitlement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'granted_at' => 'datetime',
    ];

    public function media()
    {
        return $this->belongsTo(EventsImges::class, 'media_id');
    }
}
