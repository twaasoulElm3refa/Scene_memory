<?php

namespace App\Http\Controllers\api\admin\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Auth\AdminAuthRepositoryInterface;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminAuthRepositoryInterface $authRepository)
    {

    }
    public function login(Request $request)
    {
        $user= $this->authRepository->login($request->all());
        if(!$user || $user->role != 'admin') {
            return $this->error('Invalid credentials', 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success(['token' => $token, 'user' => $user], 'admin logged in successfully');
    }
}
