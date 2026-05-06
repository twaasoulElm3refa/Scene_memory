<?php

namespace App\Http\Controllers\api\owner;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserWithdrawlController extends Controller
{
    use ApiResponse;

    private function withdrawCacheKey(int $userId): string
    {
        return 'user_withdrawals_' . $userId;
    }

    private function withdrawCacheTags(int $userId): array
    {
        return ['withdrawals', 'user_' . $userId];
    }

    private function forgetWithdrawalsCache(int $userId): void
    {
        Cache::tags($this->withdrawCacheTags($userId))
            ->forget($this->withdrawCacheKey($userId));
    }

    public function myWithdrawals(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }

        $cacheKey = $this->withdrawCacheKey($user->id);

        $withdrawals = Cache::tags($this->withdrawCacheTags($user->id))
            ->remember($cacheKey, now()->addMinutes(10), function () use ($user) {
                return withdraw::where('user_id', $user->id)
                    ->latest()
                    ->get();
            });

        return $this->success($withdrawals, 'withdrawals fetched successfully');
    }

    public function requestWithdrawals(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }

        $data = $request->all();
        $data['user_id'] = $user->id;

        $withdrawals = withdraw::create($data)->fresh();

        $this->forgetWithdrawalsCache($user->id);

        return $this->success($withdrawals, 'withdrawals fetched successfully');
    }
}
