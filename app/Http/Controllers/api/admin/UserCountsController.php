<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Users\UserRepositoryInterface;
use Illuminate\Support\Carbon;

class UserCountsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

     public function count()
    {
        $all = $this->userRepository->count();

        return $this->success($all, 'User Count');
    }

      public function last_login()
    {
       $users = $this->userRepository->countByDate('last_login_at', Carbon::today());

        return $this->success($users, 'User Last Login Counts');
    }

    public function NewUsers()
    {
        $users = $this->userRepository->countByDate('created_at', Carbon::today());

        return $this->success($users, 'New Users Counts');
    }
}
