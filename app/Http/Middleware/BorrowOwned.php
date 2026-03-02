<?php

namespace App\Http\Middleware;

use App\Http\Controllers\api\auth\apiResponse;
use App\Models\borrows;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BorrowOwned
{
    use apiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(Auth::check()){
            $borrow = borrows::where('user_id',Auth::user()->id )->first();
            if(!$borrow){
                return $this->sendError('You are not the owner of this borrow');
            }
        }
        return $next($request);
    }
}
