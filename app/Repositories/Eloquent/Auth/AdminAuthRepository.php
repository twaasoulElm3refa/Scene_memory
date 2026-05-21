<?php

namespace App\Repositories\Eloquent\Auth;

use App\Models\User;
use App\Repositories\Contracts\Auth\AdminAuthRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Override;

class AdminAuthRepository implements AdminAuthRepositoryInterface
{
    #[Override]
    public function login($request)
    {
        $credentials = $request;
        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return false;
        }
        return $user;
    }
}
