<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoreyTranslations extends Model
{
    protected $table='categorey_translations';
    protected $guarded=[]; 

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

}
