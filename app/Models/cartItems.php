<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class cartItems extends Model
{
    protected $table ='cart_items';
    protected $guarded = [];

    public function cart()
    {
        return $this->belongsTo(cart::class,'cart_id');
    }

    public function items()
    {
        return $this->belongsTo(eventsImges::class,'image_id');
    }

}
