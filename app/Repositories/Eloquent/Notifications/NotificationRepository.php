<?php

namespace App\Repositories\Eloquent\Notifications;

use App\Models\User;
use App\Repositories\Contracts\Notifications\NotificationRepositoryInterface;
use Closure;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function chunkUsers(int $size, Closure $callback): void
    {
        User::select('id', 'email')->chunk($size, $callback);
    }
}
