<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialHold extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_minor' => 'integer',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Minor units an active hold keeps out of withdrawals for this wallet. A locking
     * read sees the latest committed holds even when the caller's transaction
     * already has an older MySQL snapshot.
     */
    public static function heldMinor(int $walletId): int
    {
        return (int) static::query()->active()->where('wallet_id', $walletId)->lockForUpdate()->sum('amount_minor');
    }
}
