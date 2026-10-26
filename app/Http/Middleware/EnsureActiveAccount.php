<?php

namespace App\Http\Middleware;

use App\Support\SecurityMessage;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum');
        if ($user) {
            if ($user->is_banned) {
                return response()->json([
                    'message' => $user->ban_reason ? "Account is banned: {$user->ban_reason}" : 'This account has been banned.',
                    'ban_reason' => $user->ban_reason,
                ], 403);
            }

            if (! $user->is_active) {
                return response()->json(['message' => SecurityMessage::get('disabled')], 403);
            }
        }

        return $next($request);
    }
}
