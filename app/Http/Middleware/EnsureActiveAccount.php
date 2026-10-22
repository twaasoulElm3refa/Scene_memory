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
        if ($user && ! $user->is_active) {
            return response()->json(['message' => SecurityMessage::get('disabled')], 403);
        }

        return $next($request);
    }
}
