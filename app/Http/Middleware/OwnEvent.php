<?php

namespace App\Http\Middleware;

use App\Http\Controllers\concerns\ApiResponse;
use App\Models\Events;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnEvent
{
    use ApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(auth()->check()){
            $event = Events::where('user_id',auth()->user()->id )->first();
            if(!$event){
                return $this->unauthorized('You are not the owner of this event');
            }
        }
        return $next($request);
    }
}
