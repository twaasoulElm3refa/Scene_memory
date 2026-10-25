<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenceType extends Model
{
    protected $table = 'licence_types';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'photo_quota' => 'integer',
        'video_quota' => 'integer',
        'max_accumulated_photos' => 'integer',
        'max_accumulated_videos' => 'integer',
        'is_active' => 'boolean',
        'financial_settings_version' => 'integer',
        'financial_analysis' => 'array',
        'financially_validated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->hasMany(User::class, 'licence_type_id');
    }

    public function subscription()
    {
        return $this->hasMany(Subscriptions::class, 'licence_id');
    }

    public function translations()
    {
        return $this->hasMany(PlanTranslations::class, 'plan_id');
    }

    public function translation()
    {
        return $this->hasOne(PlanTranslations::class, 'plan_id')
            ->where('locale', app()->getLocale());
    }

    public function advantges()
    {
        return $this->hasMany(PlanBenefits::class, 'plan_id');
    }

    public function mediaTier()
    {
        return $this->belongsTo(MediaTier::class, 'media_tier_id');
    }

    public function tierLimits(): HasMany
    {
        return $this->hasMany(PlanTierLimit::class, 'licence_id')->orderBy('media_tier_id');
    }

    public function maxEligiblePhotoPrice(): float
    {
        return $this->mediaTier ? $this->mediaTier->maxEligiblePhotoPrice() : 0.0;
    }

    public function maxEligibleVideoPrice(): float
    {
        return $this->mediaTier ? $this->mediaTier->maxEligibleVideoPrice() : 0.0;
    }

    public function calculateMaxPotentialValue(): float
    {
        $photoVal = (int) $this->photo_quota * $this->maxEligiblePhotoPrice();
        $videoVal = (int) $this->video_quota * $this->maxEligibleVideoPrice();

        return round($photoVal + $videoVal, 2);
    }

    public function calculateMaxCreatorLiability(): float
    {
        $photoCap = $this->mediaTier ? (float) $this->mediaTier->payout_cap_photo : 0.0;
        $videoCap = $this->mediaTier ? (float) $this->mediaTier->payout_cap_video : 0.0;

        return round(((int) $this->photo_quota * $photoCap) + ((int) $this->video_quota * $videoCap), 2);
    }

    public function isFinanciallyValid(): bool
    {
        if ((float) $this->price <= 0 && ((int) $this->photo_quota > 0 || (int) $this->video_quota > 0)) {
            return false;
        }

        return $this->calculateMaxPotentialValue() <= (float) $this->price;
    }
}
