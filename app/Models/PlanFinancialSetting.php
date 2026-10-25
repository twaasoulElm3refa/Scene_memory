<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanFinancialSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'minimum_profit_minor' => 'integer',
        'minimum_margin_bps' => 'integer',
        'operating_cost_bps' => 'integer',
        'fixed_operating_cost_minor' => 'integer',
        'risk_reserve_bps' => 'integer',
        'tax_bps' => 'integer',
        'photo_allocation_bps' => 'integer',
        'tier_distribution' => 'array',
        'is_active' => 'boolean',
    ];

    public function paymentFees(): HasMany
    {
        return $this->hasMany(PlanPaymentFeeRule::class);
    }
}
