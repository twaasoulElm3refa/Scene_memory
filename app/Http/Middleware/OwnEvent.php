<?php

namespace App\Http\Middleware;

use App\Http\Controllers\concerns\ApiResponse;
use App\Models\Events;
use App\Support\EventAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnEvent
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('slug')
            ? Events::where('slug', $request->route('slug'))->first()
            : Events::find($request->route('id'));
        EventAccess::authorize($event, $request->user());

        return $next($request);
    }
}
