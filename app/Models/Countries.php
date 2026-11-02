<?php

namespace App\Models;
use App\Models\Concerns\ResolvesCloudinaryImage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Countries extends Model
{
    /** @use HasFactory<\Database\Factories\CountriesFactory> */
    use HasFactory;

    use SoftDeletes, ResolvesCloudinaryImage;

    protected $guarded = [];

    protected $table = 'countries';

    public function cities()
    {
        return $this->hasMany(Cities::class, 'country_id');
    }

    public function translations()
    {
        return $this->hasMany(CountryTranslations::class, 'country_id');
    }

    public function translation()
    {
        return $this->hasOne(CountryTranslations::class, 'country_id')
            ->where('locale', app()->getLocale());
    }
}
