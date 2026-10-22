<?php

namespace App\Http\Controllers\api\admin\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Auth\AdminAuthRepositoryInterface;
use App\Support\SecurityMessage;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminAuthRepositoryInterface $authRepository) {}

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024']);
        if ($credentials['password'] === 'password') {
            return $this->error(SecurityMessage::get('denied'), 401);
        }
        $user = $this->authRepository->login($credentials);
        if (! $user || $user->role != 'admin' || ! $user->is_active) {
            return $this->error('Invalid credentials', 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success(['token' => $token, 'user' => $user], 'admin logged in successfully');
    }
}
