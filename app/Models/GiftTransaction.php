<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftTransaction extends Model
{
    protected $guarded = [];

    protected $casts = ['gift_snapshot' => 'array', 'quantity' => 'integer', 'unit_price_minor' => 'integer', 'gross_amount_minor' => 'integer', 'platform_commission_minor' => 'integer', 'recipient_earning_minor' => 'integer', 'payment_processing_fee_minor' => 'integer', 'processing_fee_precision' => 'integer', 'refunded_gross_minor' => 'integer', 'reversed_commission_minor' => 'integer', 'reversed_earning_minor' => 'integer', 'review_required' => 'boolean', 'disputed' => 'boolean', 'paid_at' => 'datetime', 'delivered_at' => 'datetime', 'release_at' => 'datetime', 'settled_at' => 'datetime', 'last_reconciled_at' => 'datetime'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function earning()
    {
        return $this->hasOne(GiftEarningEntry::class);
    }
}
