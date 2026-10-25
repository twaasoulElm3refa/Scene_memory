<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPaymentFeeRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'percentage_bps' => 'integer',
        'fixed_fee_minor' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(PlanFinancialSetting::class, 'plan_financial_setting_id');
    }
}
