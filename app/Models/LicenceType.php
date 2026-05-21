<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenceType extends Model
{
    protected $table ='licence_types';
    protected $guarded = [];

    public function user()
    {
        return $this->hasMany(User::class , 'licence_type_id');
    }

    public function subscription()
    {
        return $this->hasMany(Subscriptions::class,'licence_id');
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
}
