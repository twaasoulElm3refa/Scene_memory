<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PointService
{
    public const EVENT_CREATED = 'event.created';

    public const LIKE_CREATED = 'like.created';

    public const COMMENT_CREATED = 'comment.created';

    public const COMMENT_REPLY = 'comment.reply';

    public const COMMENT_IMAGE = 'comment.image';

    public const COMMENT_INTERACTION = 'comment.interaction';

    public const WISHLIST_CREATED = 'wishlist.created';

    public function __construct(private readonly MonthlyPointService $monthlyPointService) {}

    /**
     * Award an active point rule once for the same user, action, and reference.
     */
    public function award(User $user, string $action, Model $reference, array $metadata = []): bool
    {
        if (! $reference->exists) {
            return false;
        }

        return $this->monthlyPointService->addPoints(
            $user,
            $action,
            $reference->getMorphClass(),
            (int) $reference->getKey(),
            $metadata
        );
    }
}
