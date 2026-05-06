<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $table = 'wallets';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransactions::class,'wallet_id');
    }

}
