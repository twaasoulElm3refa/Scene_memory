<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItems extends Model
{
    protected $table='purchase_items';

    protected $guarded = [];

    public function purchases()
    {
        return $this->belongsTo(Purchases::class,'purchase_id');
    }
}
