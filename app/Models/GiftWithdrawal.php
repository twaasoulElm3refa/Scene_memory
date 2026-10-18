<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftWithdrawal extends Model
{
    protected $guarded = [];

    protected $casts = ['payment_details' => 'encrypted:array', 'amount_minor' => 'integer', 'available_balance_at_request_minor' => 'integer', 'paid_at' => 'datetime', 'approved_at' => 'datetime', 'processing_at' => 'datetime', 'rejected_at' => 'datetime', 'failed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
