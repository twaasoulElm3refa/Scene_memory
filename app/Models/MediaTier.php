<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaTier extends Model
{
    use SoftDeletes;

    protected $table = 'media_tiers';

    protected $guarded = [];

    protected $casts = [
        'rank' => 'integer',
        'min_price_photo' => 'decimal:2',
        'max_price_photo' => 'decimal:2',
        'min_price_video' => 'decimal:2',
        'max_price_video' => 'decimal:2',
        'payout_cap_photo' => 'decimal:2',
        'payout_cap_video' => 'decimal:2',
        'is_active' => 'boolean',
        'is_plan_eligible' => 'boolean',
    ];

    protected $appends = [
        'min_photo_price',
        'max_photo_price',
        'max_photo_payout_cap',
        'min_video_price',
        'max_video_price',
        'max_video_payout_cap',
    ];

    public function getMinPhotoPriceAttribute(): float
    {
        return (float) ($this->attributes['min_price_photo'] ?? 0);
    }

    public function setMinPhotoPriceAttribute($value): void
    {
        $this->attributes['min_price_photo'] = $value;
    }

    public function getMaxPhotoPriceAttribute(): float
    {
        return (float) ($this->attributes['max_price_photo'] ?? 0);
    }

    public function setMaxPhotoPriceAttribute($value): void
    {
        $this->attributes['max_price_photo'] = $value;
    }

    public function getMaxPhotoPayoutCapAttribute(): float
    {
        return (float) ($this->attributes['payout_cap_photo'] ?? 0);
    }

    public function setMaxPhotoPayoutCapAttribute($value): void
    {
        $this->attributes['payout_cap_photo'] = $value;
    }

    public function getMinVideoPriceAttribute(): float
    {
        return (float) ($this->attributes['min_price_video'] ?? 0);
    }

    public function setMinVideoPriceAttribute($value): void
    {
        $this->attributes['min_price_video'] = $value;
    }

    public function getMaxVideoPriceAttribute(): float
    {
        return (float) ($this->attributes['max_price_video'] ?? 0);
    }

    public function setMaxVideoPriceAttribute($value): void
    {
        $this->attributes['max_price_video'] = $value;
    }

    public function getMaxVideoPayoutCapAttribute(): float
    {
        return (float) ($this->attributes['payout_cap_video'] ?? 0);
    }

    public function setMaxVideoPayoutCapAttribute($value): void
    {
        $this->attributes['payout_cap_video'] = $value;
    }

    public function plans(): HasMany
    {
        return $this->hasMany(LicenceType::class, 'media_tier_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(EventsImges::class, 'media_tier_id');
    }

    /**
     * Scope for active plan-eligible tiers ordered by rank.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('rank', 'asc');
    }

    /**
     * Get maximum eligible price for photo across this tier and all lower tiers.
     */
    public function maxEligiblePhotoPrice(): float
    {
        return (float) static::where('rank', '<=', $this->rank)
            ->where('is_active', true)
            ->max('max_price_photo');
    }

    /**
     * Get maximum eligible price for video across this tier and all lower tiers.
     */
    public function maxEligibleVideoPrice(): float
    {
        return (float) static::where('rank', '<=', $this->rank)
            ->where('is_active', true)
            ->max('max_price_video');
    }
}
