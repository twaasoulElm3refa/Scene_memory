<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class purchase_items extends Model
{
    protected $table='purchase_items';

    protected $guarded = [];

    public function purchases()
    {
        return $this->belongsTo(purchases::class,'purchase_id');
    }
}
