<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class subCategorey extends Model
{
    protected $table = "sub_categoreys";
    protected $guarded = [];
    public function events()
    {
        return $this->hasMany(Events::class,'sub_categorey_id');
    }

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }
}
