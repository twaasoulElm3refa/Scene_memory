<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\Withdrawals\WithdrawalRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawlController extends Controller
{
    use ApiResponse;
    private $cacheTime=60;

    public function __construct(private readonly WithdrawalRepositoryInterface $withdrawalRepository)
    {
    }

    public function index(Request $request)
    {
        try {
            $version = Cache::get('withdrawls_version', 1);

            $cacheKey = "withdrawls_v{$version}_" . md5(json_encode($request->all()));
            $withdrawls = Cache::tags(['withdrawls'])->remember(
                $cacheKey,
                now()->addMinutes(5),
                function () {
                    return $this->withdrawalRepository->paginated(10);
                }
            );

            return $this->success($withdrawls,'withdrawls fetched successfully');

        } catch (\Throwable $e) {
            Log::error('Withdraw Index Error: ' . $e->getMessage());

            return $this->error($e->getMessage());
        }
    }

    public function count()
    {
        try {
            $version = Cache::get('withdrawls_version', 1);
            $cacheKey = "withdrawls_count_v{$version}";
            $count = Cache::tags(['withdrawls'])->remember(
                $cacheKey,
                now()->addMinutes($this->cacheTime),
                function () {
                    return $this->withdrawalRepository->count();
                }
            );
            return $this->success($count);

        } catch (\Throwable $e) {
            Log::error('Withdraw Count Error: ' . $e->getMessage());

            // fallback لو الكاش ضرب
            return $this->success($this->withdrawalRepository->count());
        }
    }

    public function status($status)
    {
        try {
            $page = (int) request()->get('page', 1);
            $cacheKey = 'withdrawls_all_page_' . $page .'_'. $status;
            $withdrawls = Cache::tags(['withdrawls'])->remember(
                $cacheKey,
                now()->addMinutes($this->cacheTime),
                fn () => $this->withdrawalRepository->paginatedByStatus($status, 10)
            );
            return $this->success($withdrawls);
        } catch (\Throwable $e) {
            Log::error('Withdraw Status Error: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            return $this->success($this->withdrawalRepository->find((int) $id));
        } catch (\Throwable $e) {
            Log::error('Withdraw Show Error: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $this->clearWithdrawCache();
            return $this->success($this->withdrawalRepository->find((int) $id)->update($request->all()));
        } catch (\Throwable $e) {
            Log::error('Withdraw Update Error: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
    public function destroy(Request $request, $id)
    {
        try {
            $this->clearWithdrawCache();
            return $this->success($this->withdrawalRepository->find((int) $id)->delete());
        } catch (\Throwable $e) {
            Log::error('Withdraw Update Error: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    public function approve($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $withdraw = $this->withdrawalRepository->findOrFail((int) $id);
                $user=User::find($withdraw->user_id);
                $wallet=$user->wallet;
                $wallet->update([
                    'pending'=>0
                ]);
                if ($withdraw->status === 'completed') {
                    return $this->error('Withdraw already approved');
                }

                $withdraw->update([
                    'status' => 'completed',
                    'approved_by' => auth()->id(),
                    'processed_at' => now(),
                ]);
                $this->clearUserProfileCache($user->id);
                $this->clearWithdrawCache();
                return $this->success($withdraw->fresh());
            });

        } catch (\Throwable $e) {
            Log::error('Withdraw Approve Error: ' . $e->getMessage());
            return $this->error('Something went wrong');
        }
    }

    public function reject($id)
    {
         try {
            return DB::transaction(function () use ($id) {
                $withdraw = $this->withdrawalRepository->findOrFail((int) $id);
                if ($withdraw->status === 'rejected') {
                    return $this->error('Withdraw already rejected');
                }
                $user=User::find($withdraw->user_id);
                $wallet=$user->wallet;
                $wallet->update([
                    'amount'=> $wallet->amount + $wallet->pending,
                    'pending'=>0,
                ]);
                $withdraw->update([
                    'status' => 'rejected',
                    'approved_by' => auth()->id(),
                    'processed_at' => now(),
                ]);
                $this->clearUserProfileCache($user->id);
                $this->clearWithdrawCache();
                return $this->success($withdraw->fresh());

            });

        } catch (\Throwable $e) {
            Log::error('Withdraw Reject Error: ' . $e->getMessage());

            return $this->error($e->getMessage());
        }
    }

    private function clearWithdrawCache()
    {
        Cache::increment('withdrawls_version');
        Cache::tags(['withdrawls'])->flush();
    }

    public function clearUserProfileCache($userId): void
    {
        $cacheKey = 'user_profile_' . $userId;

        Cache::tags(['user_profile', 'user_'.$userId])->forget($cacheKey);
    }
}
