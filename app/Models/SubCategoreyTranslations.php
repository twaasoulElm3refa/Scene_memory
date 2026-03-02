<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCategoreyTranslations extends Model
{
    protected $table = "sub_categorey_translations";
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(subCategorey::class, 'category_id');
    }
}
