<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactResponds extends Model
{
    protected $guarded = [];

    public function contacts()
    {
        return $this->belongsTo(Contacts::class,'contact_id');
    }
}
