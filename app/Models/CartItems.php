<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItems extends Model
{
    protected $table ='cart_items';
    protected $guarded = [];

    public function cart()
    {
        return $this->belongsTo(Cart::class,'cart_id');
    }

    public function items()
    {
        return $this->belongsTo(EventsImges::class,'image_id');
    }

}
