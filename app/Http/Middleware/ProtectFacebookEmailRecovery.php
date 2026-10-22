<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SecurityMessage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProtectFacebookEmailRecovery
{
    public function handle(Request $request, Closure $next)
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $account = User::whereNotNull('facebook_id')->whereNull('email_verified_at')
                ->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if ($account) {
                $actor = $request->user('sanctum');
                // A provider-reported address is contact data, not an alternative
                // credential. Require the Facebook-authenticated owner before using
                // that address to set up local verification/password recovery.
                if ($request->is('api/v1/users/register') || ! $account->is_active || $account->deleted_at !== null
                    || ! $actor || $actor->getKey() !== $account->getKey()) {
                    return response()->json(['message' => SecurityMessage::get('denied')], 403);
                }
            }
        }

        return $next($request);
    }
}
