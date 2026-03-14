<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriptions extends Model
{
    protected $table = 'subscriptions';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function licence()
    {
        return $this->belongsTo(licenceType::class,'licence_id');
    }
}
