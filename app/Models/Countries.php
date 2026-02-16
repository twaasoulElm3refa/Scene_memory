<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Countries extends Model
{
    /** @use HasFactory<\Database\Factories\CountriesFactory> */
    use HasFactory;

    protected $guarded = [];
    protected $table = "countries";
    public function cities()
    {
        return $this->hasMany(Cities::class,'country_id');
    }
}
