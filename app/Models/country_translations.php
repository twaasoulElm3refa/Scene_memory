<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class country_translations extends Model
{
    protected $table = 'country_translations';

    protected $guarded = [];

    public function country()
    {
        return $this->belongsTo(Countries::class, 'country_id');
    }
}
