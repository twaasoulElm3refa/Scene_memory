<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class licenceType extends Model
{
    protected $table ='licence_types';
    protected $guarded = [];

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function subscription()
    {
        return $this->hasMany(Subscriptions::class,'licence_id');
    }
}
