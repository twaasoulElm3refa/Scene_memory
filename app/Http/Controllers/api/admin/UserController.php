<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Repositories\Contracts\Users\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function index()
    {
        $all = Cache::remember('users_latest_10', 1200, function () {
            return $this->userRepository->latestActive(10);
        });

        return $this->success($all, 'All User');
    }

    public function all()
    {
        $cacheKey = 'users_all_page_'.request('page', 1);
        $all = Cache::remember($cacheKey, 1200, function () {
            return $this->userRepository->latestPaginated(10);
        });

        return $this->success($all, 'All User');
    }

    public function show()
    {
        $user = $this->userRepository->findById((int) request('id'));
        if (! $user) {
            return $this->success([], 'user Not Found');
        }

        return $this->success($user, 'user');

    }

    public function create(UserRequest $request)
    {
        $data=$request->validated();
        try {
            $data['password'] = bcrypt($data['password']);
            $data['is_active'] = 1;
            $data['last_login_at'] = now();
            $user = $this->userRepository->create($data);
            Cache::forget('users_all_page_'.request('page', 1));
            return $this->success($user, 'user Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }

    }

    public function update(Request $request)
    {
        $data = $request->all();
        $user = $this->userRepository->findById((int) request('id'));
        if (! $user) {
            return $this->success([], 'user Not Found');
        }
        $user->update($data);

        return $this->success($user, 'user Updated Successfully');
    }

    public function destroy()
    {
        $user = $this->userRepository->findById((int) request('id'));
        if (! $user) {
            return $this->success([], 'user Not Found');
        }
        $user->delete();

        return $this->success($user, 'user Deleted Successfully');
    }

    public function latest()
    {
        $users = $this->userRepository->latest(5);
        return $this->success($users,'Last 5 users ');
    }
}
