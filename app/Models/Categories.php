<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    /** @use HasFactory<\Database\Factories\CategoriesFactory> */
    use HasFactory;

    protected $table = "categories";
    protected $guarded = [];

    public function subCategories()
    {
        return $this->hasMany(subCategorey::class,'category_id');
    }

    
}
