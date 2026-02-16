<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cities extends Model
{
    /** @use HasFactory<\Database\Factories\CitiesFactory> */
    use HasFactory;

    protected $table = "cities";
    protected $guarded = [];

    public function countries()
    {
        return $this->belongsTo(Countries::class,"country_id");
    }

    public function events()
    {
        return $this->hasMany(Events::class, 'city_id');
    }
}
