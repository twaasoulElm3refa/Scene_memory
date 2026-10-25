<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanTierLimit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'photo_maximum' => 'integer',
        'video_maximum' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LicenceType::class, 'licence_id');
    }

    public function mediaTier(): BelongsTo
    {
        return $this->belongsTo(MediaTier::class);
    }
}
