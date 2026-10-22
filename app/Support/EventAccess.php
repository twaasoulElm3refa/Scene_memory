<?php

namespace App\Support;

use App\Models\Events;
use App\Models\User;

class EventAccess
{
    public static function authorize(?Events $event, ?User $user): void
    {
        abort_unless($event, 404, SecurityMessage::get('notFound'));
        abort_unless($user && $user->is_active && ($user->role === 'admin' || (int) $event->user_id === (int) $user->id), 403, SecurityMessage::get('denied'));
    }
}
