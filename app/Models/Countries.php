<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Countries extends Model
{
    /** @use HasFactory<\Database\Factories\CountriesFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'countries';

    public function cities()
    {
        return $this->hasMany(Cities::class, 'country_id');
    }

    public function translations()
    {
        return $this->hasMany(country_translations::class, 'country_id');
    }

    public function translation()
    {
        return $this->hasOne(country_translations::class, 'country_id')
            ->where('locale', app()->getLocale());
    }
}
