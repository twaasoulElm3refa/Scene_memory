<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockBadBots
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $userAgent = strtolower($request->header('User-Agent', ''));
        $blockedAgents = ['curl', 'python-requests', 'scrapy'];
        foreach ($blockedAgents as $agent) {
            if (str_contains($userAgent, $agent)) {
                return response()->json([
                    'message' => 'Access denied for bots',
                ], 403);
            }
        }

        return $next($request);
    }
}
