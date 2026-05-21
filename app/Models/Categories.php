<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    /** @use HasFactory<\Database\Factories\CategoriesFactory> */
    use HasFactory;

    protected $table = 'categories';

    protected $guarded = [];

    public function subCategories()
    {
        return $this->hasMany(SubCategorey::class, 'category_id');
    }

    public function translations()
    {
        return $this->hasMany(CategoreyTranslations::class, 'category_id');
    }

    public function translation()
    {
        return $this->hasOne(CategoreyTranslations::class, 'category_id')
            ->where('locale', app()->getLocale());
    }
}
