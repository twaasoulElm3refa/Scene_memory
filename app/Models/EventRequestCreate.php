<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRequestCreate extends Model
{
    protected $table = 'event_request_creates';

    protected $guarded = [];

    protected $casts = [
        'ai_flagged' => 'boolean',
        'ai_confidence' => 'decimal:4',
        'ai_raw_response' => 'array',
        'ai_reviewed_at' => 'datetime',
        'ai_attempts' => 'integer',
    ];

    public function events()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }
}
