<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserCreditGrant extends Model
{
    protected $table = 'user_credit_grants';

    protected $guarded = [];

    protected $casts = [
        'tier_rank' => 'integer',
        'photo_count' => 'integer',
        'video_count' => 'integer',
        'remaining_photos' => 'integer',
        'remaining_videos' => 'integer',
        'max_eligible_photo_price' => 'decimal:2',
        'max_eligible_video_price' => 'decimal:2',
        'payout_cap_photo' => 'decimal:2',
        'payout_cap_video' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LicenceType::class, 'licence_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchases::class, 'purchase_id');
    }

    public function mediaTier(): BelongsTo
    {
        return $this->belongsTo(MediaTier::class, 'media_tier_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'grant_id');
    }
}
