<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaypalWebhookEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
