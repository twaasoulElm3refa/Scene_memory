<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    protected $table = 'credit_transactions';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(UserCreditGrant::class, 'grant_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(EventsImges::class, 'media_id');
    }
}
