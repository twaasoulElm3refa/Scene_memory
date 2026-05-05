<?php

namespace App\Repositories\Contracts\Notifications;

use Closure;

interface NotificationRepositoryInterface
{
    public function chunkUsers(int $size, Closure $callback): void;
}
