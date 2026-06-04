<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItems extends Model
{
    protected $table ='cart_items';
    protected $guarded = [];
    protected $casts = [
        'collection_images' => 'array', // Auto-convert JSON to array
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function getCollectionImagesArrayAttribute(): array
    {
        if (is_array($this->collection_images)) {
            return $this->collection_images;
        }

        if (is_string($this->collection_images)) {
            $decoded = json_decode($this->collection_images, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function getFinalPriceAttribute(): string
    {
        $price = (float) ($this->price ?? 0);
        $discount = (float) ($this->discount ?? 0);

        if ($this->type === 'collection') {
            return number_format(max($price - $discount, 0), 2, '.', '');
        }

        return number_format($price, 2, '.', '');
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class,'cart_id');
    }

    public function items()
    {
        return $this->belongsTo(EventsImges::class,'image_id');
    }

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }
}
