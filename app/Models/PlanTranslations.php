<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanTranslations extends Model
{
   protected $table ='plan_translations';

   protected $guarded = [];

   public function licence()
   {
    return $this->belongsTo(LicenceType::class,'plan_id');
   }
}
