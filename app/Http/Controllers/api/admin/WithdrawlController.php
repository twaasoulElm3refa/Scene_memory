<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Withdrawals\WithdrawalRepositoryInterface;
use App\Services\WithdrawalReservationService;
use App\Support\SecurityMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WithdrawlController extends Controller
{
    use ApiResponse;

    private $cacheTime = 60;

    public function __construct(private readonly WithdrawalRepositoryInterface $withdrawalRepository) {}

    public function index(Request $request)
    {
        try {
            $version = Cache::get('withdrawls_version', 1);

            $cacheKey = "withdrawls_v{$version}_".md5(json_encode($request->all()));
            $withdrawls = Cache::tags(['withdrawls'])->remember(
                $cacheKey,
                now()->addMinutes(5),
                function () {
                    return $this->withdrawalRepository->paginated(10);
                }
            );

            return $this->success($withdrawls, 'withdrawls fetched successfully');

        } catch (\Throwable $e) {
            Log::error('Withdraw Index Error: '.$e->getMessage());

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
            Log::error('Withdraw Count Error: '.$e->getMessage());

            // fallback لو الكاش ضرب
            return $this->success($this->withdrawalRepository->count());
        }
    }

    public function status($status)
    {
        try {
            $page = (int) request()->get('page', 1);
            $cacheKey = 'withdrawls_all_page_'.$page.'_'.$status;
            $withdrawls = Cache::tags(['withdrawls'])->remember(
                $cacheKey,
                now()->addMinutes($this->cacheTime),
                fn () => $this->withdrawalRepository->paginatedByStatus($status, 10)
            );

            return $this->success($withdrawls);
        } catch (\Throwable $e) {
            Log::error('Withdraw Status Error: '.$e->getMessage());

            return $this->error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            return $this->success($this->withdrawalRepository->find((int) $id));
        } catch (\Throwable $e) {
            Log::error('Withdraw Show Error: '.$e->getMessage());

            return $this->error($e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'amount' => 'sometimes|numeric|gt:0|max:99999999.99', 'fee' => 'sometimes|numeric|min:0|max:99999999.99',
            'status' => 'sometimes|in:pending,processing,completed,rejected,cancelled,approved,paid',
            'admin_note' => 'sometimes|nullable|string|max:8000', 'mail_sent' => 'sometimes|boolean',
            'payment_details' => 'sometimes|nullable|array|max:50',
            'currency' => 'sometimes|string|size:3', 'method' => 'sometimes|nullable|string|max:100',
            'reference' => 'sometimes|nullable|string|max:191', 'transaction_id' => 'sometimes|nullable|string|max:191',
        ]);
        if (isset($data['status']) && in_array($data['status'], ['approved', 'paid'], true)) {
            $data['status'] = 'completed';
        }
        if (isset($data['payment_details']) && strlen(json_encode($data['payment_details'])) > 8000) {
            throw ValidationException::withMessages(['payment_details' => SecurityMessage::get('invalidWithdrawal')]);
        }
        $result = app(WithdrawalReservationService::class)->update($request->user(), (int) $id, $data);
        $this->clearWithdrawCache();

        return $this->success($result);
    }

    public function destroy(Request $request, $id)
    {
        return $this->reject($id);
    }

    public function approve($id)
    {
        $result = app(WithdrawalReservationService::class)->transition(request()->user(), (int) $id, 'completed');
        $this->clearUserProfileCache($result->user_id);
        $this->clearWithdrawCache();

        return $this->success($result);
    }

    public function reject($id)
    {
        $result = app(WithdrawalReservationService::class)->transition(request()->user(), (int) $id, 'rejected');
        $this->clearUserProfileCache($result->user_id);
        $this->clearWithdrawCache();

        return $this->success($result);
    }

    private function clearWithdrawCache()
    {
        Cache::increment('withdrawls_version');
        Cache::tags(['withdrawls'])->flush();
    }

    public function clearUserProfileCache($userId): void
    {
        $cacheKey = 'user_profile_'.$userId;

        Cache::tags(['user_profile', 'user_'.$userId])->forget($cacheKey);
    }
}
