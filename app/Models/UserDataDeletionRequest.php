<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDataDeletionRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public const TYPE_FULL_ACCOUNT = 'full_account_deletion';
    public const TYPE_FACEBOOK_DATA = 'facebook_data_deletion';

    protected $fillable = [
        'reference',
        'user_id',
        'user_identifier',
        'masked_email',
        'request_type',
        'status',
        'reason',
        'requires_code',
        'confirmation_code_hash',
        'confirmation_code_expires_at',
        'confirmation_code_attempts',
        'financial_obligations_detected',
        'financial_hold_reason',
        'retention_policy_applied',
        'admin_notes',
        'failure_reason',
        'ip_address',
        'user_agent',
        'confirmed_at',
        'processed_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_code' => 'boolean',
            'financial_obligations_detected' => 'boolean',
            'retention_policy_applied' => 'array',
            'confirmation_code_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_CONFIRMATION,
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
        ], true);
    }

    public function isPendingConfirmation(): bool
    {
        return $this->status === self::STATUS_PENDING_CONFIRMATION;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
