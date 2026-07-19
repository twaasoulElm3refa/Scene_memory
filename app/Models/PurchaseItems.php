<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItems extends Model
{
    protected $table = 'purchase_items';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'snapshot' => 'array',
    ];

    public function purchases()
    {
        return $this->belongsTo(Purchases::class, 'purchase_id');
    }

    public function image()
    {
        return $this->belongsTo(EventsImges::class, 'image_id');
    }
}
