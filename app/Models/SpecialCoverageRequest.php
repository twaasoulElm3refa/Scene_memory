<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialCoverageRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const EVENT_TYPE_PERSONAL = 'personal';
    public const EVENT_TYPE_PUBLIC = 'public';
    public const EVENT_TYPES = [
        self::EVENT_TYPE_PERSONAL,
        self::EVENT_TYPE_PUBLIC,
    ];

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Countries::class, 'country_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class, 'city_id');
    }
}
