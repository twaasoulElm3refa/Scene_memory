<?php

namespace App\Http\Middleware;

use App\Http\Controllers\concerns\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnComment
{
    use ApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $comment = $request->input("comment");
        if($request->user()->id != $comment->user_id){
            return $this->unauthorized('You are not the owner of this comment');
        }
        return $next($request);
    }
}
