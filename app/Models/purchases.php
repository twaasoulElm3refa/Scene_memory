<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class purchases extends Model
{
    protected $table = 'purchases';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function items()
    {
        return $this->hasMany(purchase_items::class,'purchase_id');
    }
}
