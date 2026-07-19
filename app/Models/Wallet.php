<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $table = 'wallets';
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_minor' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransactions::class,'wallet_id');
    }

}
