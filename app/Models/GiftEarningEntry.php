<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftEarningEntry extends Model
{
    protected $guarded = [];

    protected $casts = ['release_at' => 'datetime', 'gross_minor' => 'integer', 'commission_minor' => 'integer', 'recipient_share_minor' => 'integer', 'pending_minor' => 'integer', 'available_minor' => 'integer', 'reserved_minor' => 'integer', 'settled_minor' => 'integer', 'reversed_minor' => 'integer', 'recovery_minor' => 'integer'];

    public function transaction()
    {
        return $this->belongsTo(GiftTransaction::class, 'gift_transaction_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
