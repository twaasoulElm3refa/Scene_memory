<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenefitsTranslations extends Model
{
    protected $table ='benefits_translations';

    protected $guarded = [];

    public function benefit()
    {
        return $this->belongsTo(PlanBenefits::class, 'benefit_id');
    }
}
