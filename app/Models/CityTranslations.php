<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityTranslations extends Model
{
    protected $table = 'city_translations';

    protected $guarded=[];

    public function city()
    {
        return $this->belongsTo(Cities::class,'city_id');
    }
}
