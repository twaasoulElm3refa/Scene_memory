<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    /** @use HasFactory<\Database\Factories\WishlistFactory> */
    use HasFactory;

    protected $table = "wishlists";
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class,"user_id");
    }

    public function event()
    {
        return $this->belongsTo(Events::class,"event_id");
    }
}
