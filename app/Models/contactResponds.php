<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class contactResponds extends Model
{
    protected $guarded = [];

    public function contacts()
    {
        return $this->belongsTo(contacts::class,'contact_id');
    }
}
