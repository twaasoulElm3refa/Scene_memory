<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gateway_response' => 'array',
        'purchase_granted' => 'boolean',
        'wallet_credited' => 'boolean',
        'capture_requested_at' => 'datetime',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Purchases::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
