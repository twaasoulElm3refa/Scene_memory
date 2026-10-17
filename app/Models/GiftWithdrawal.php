<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftWithdrawal extends Model
{
    protected $guarded = [];

    protected $casts = ['payment_details' => 'encrypted:array', 'amount_minor' => 'integer', 'paid_at' => 'datetime'];
}
