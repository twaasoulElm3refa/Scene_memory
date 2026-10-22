<?php

namespace App\Http\Middleware;

use App\Support\SecurityMessage;
use Closure;
use Illuminate\Http\Request;

class SecurityResponseHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($request->is('api/v1/users/facebook-*', '*/auth/facebook-callback')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }
        if ($request->is('api/*') && ! config('app.debug') && $response->getStatusCode() >= 500) {
            $response->setContent(json_encode(['status' => 'error', 'message' => SecurityMessage::get('requestFailed')], JSON_UNESCAPED_UNICODE));
            if ($request->is('api/v1/users/google-*')) {
                $code = $response->getStatusCode() === 503 ? 'unavailable' : 'callback_failed';
                $response->setContent(json_encode(['status' => 'error', 'error_code' => $code, 'message_key' => 'googleAuth.'.$code]));
            }
            $response->headers->set('Content-Type', 'application/json');
            if ($request->is('api/v1/users/facebook-*')) {
                $code = $response->getStatusCode() === 503 ? 'unavailable' : 'callback_failed';
                $response->setContent(json_encode(['status' => 'error', 'error_code' => $code, 'message_key' => 'facebookAuth.'.$code]));
            }
        }
        if ($request->is('api/*') && $request->user('sanctum')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
