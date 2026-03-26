<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanBenefits extends Model
{
    protected $table = "plan_benefits";
    protected $guarded = [];

    public function plan()
    {
        return $this->belongsTo(licenceType::class, 'plan_id');
    }

      public function translations()
    {
        return $this->hasMany(BenefitsTranslations::class, 'benefit_id');
    }

    public function translation()
    {
        return $this->hasOne(BenefitsTranslations::class, 'benefit_id')
            ->where('locale', app()->getLocale());
    }
}
