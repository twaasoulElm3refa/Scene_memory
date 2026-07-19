<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletTransactions extends Model
{
    use SoftDeletes;
    protected $table = 'wallet_transactions';
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'amount_minor' => 'integer',
        'balance_before_minor' => 'integer',
        'balance_after_minor' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchases::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
