<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserCountsController extends Controller
{
    use ApiResponse;
     public function count()
    {
        $all = User::count();

        return $this->success($all, 'User Count');
    }

      public function last_login()
    {
       $users = User::whereDate('last_login_at', Carbon::today())
    ->orderBy('id', 'desc')
    ->count();

        return $this->success($users, 'User Last Login Counts');
    }

    public function NewUsers()
    {
        $users = User::whereDate('created_at', Carbon::today())
    ->orderBy('id', 'desc')
    ->count();

        return $this->success($users, 'New Users Counts');
    }
}
