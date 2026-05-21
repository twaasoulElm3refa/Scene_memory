<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchases extends Model
{
    protected $table = 'purchases';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseItems::class,'purchase_id');
    }

    protected $casts = [
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
        'amount'           => 'decimal:2',
    ];

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransactions::class,'purchase_id');
    }
    public function scopePending($q)   { return $q->where('status', 'pending'); }
    public function scopeCompleted($q) { return $q->where('status', 'completed'); }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
}
