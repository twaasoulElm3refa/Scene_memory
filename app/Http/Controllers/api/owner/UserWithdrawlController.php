<?php

namespace App\Http\Controllers\api\owner;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use App\Services\WithdrawalReservationService;
use App\Support\SecurityMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserWithdrawlController extends Controller
{
    use ApiResponse;

    private function withdrawalsVersionKey(int $userId): string
    {
        return 'user_withdrawals_version_'.$userId;
    }

    private function withdrawalsVersion(int $userId): int
    {
        return (int) Cache::get($this->withdrawalsVersionKey($userId), 1);
    }

    private function withdrawalsCacheKey(int $userId, array $params = []): string
    {
        $version = $this->withdrawalsVersion($userId);

        return 'user_withdrawals_'.$userId.'_v'.$version.'_'.md5(json_encode($params));
    }

    private function flushWithdrawalsCache(int $userId): void
    {
        Cache::forever(
            $this->withdrawalsVersionKey($userId),
            $this->withdrawalsVersion($userId) + 1
        );
    }

    private function normalizePaymentDetails(mixed $input, bool &$isInvalid = false): ?array
    {
        $isInvalid = false;

        if ($input === null || $input === '') {
            return null;
        }

        if (is_array($input)) {
            return $input;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                $isInvalid = true;

                return null;
            }

            return $decoded;
        }

        $isInvalid = true;

        return null;
    }

    private function findUserWithdrawal(int $userId, int $withdrawalId): ?Withdraw
    {
        return Withdraw::query()
            ->where('user_id', $userId)
            ->where('id', $withdrawalId)
            ->with(['approvedBy:id,name'])
            ->first();
    }

    public function myWithdrawals(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }

        $status = $request->query('status');
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $usePagination = $request->has('page') || $request->has('per_page') || $request->boolean('paginate', false);

        $cacheParams = [
            'status' => $status,
            'per_page' => $perPage,
            'page' => (int) $request->query('page', 1),
            'paginate' => $usePagination,
        ];

        $cacheKey = $this->withdrawalsCacheKey($user->id, $cacheParams);

        $withdrawals = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $status, $perPage, $usePagination) {
            $query = Withdraw::query()
                ->where('user_id', $user->id)
                ->with(['approvedBy:id,name'])
                ->latest();

            if ($status) {
                $query->where('status', $status);
            }

            return $usePagination ? $query->paginate($perPage) : $query->get();
        });

        return $this->success($withdrawals, 'Withdrawals fetched successfully');
    }

    public function showWithdrawals(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $withdrawal = $this->findUserWithdrawal($user->id, (int) $request->input('id'));

        if (! $withdrawal) {
            return $this->notFound('Withdrawal not found');
        }

        return $this->success($withdrawal, 'Withdrawal fetched successfully');
    }

    private function withdrawalInput(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'gt:0', 'max:99999999.99'],
            'fee' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'method' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payment_details' => ['sometimes', 'nullable'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        if (array_key_exists('payment_details', $data)) {
            $invalid = false;
            $data['payment_details'] = $this->normalizePaymentDetails($data['payment_details'], $invalid);
            if ($invalid || strlen(json_encode($data['payment_details'])) > 8000) {
                throw ValidationException::withMessages(['payment_details' => SecurityMessage::get('invalidWithdrawal')]);
            }
        }

        return $data;
    }

    public function requestWithdrawals(Request $request, $id)
    {
        $withdrawal = app(WithdrawalReservationService::class)->create($request->user(), (int) $id, $this->withdrawalInput($request, true));
        $this->flushWithdrawalsCache($request->user()->id);

        return $this->success($withdrawal, 'Withdrawal request created successfully');
    }

    public function updateWithdrawals(Request $request, $id)
    {
        $withdrawal = app(WithdrawalReservationService::class)->update($request->user(), (int) $id, $this->withdrawalInput($request, false));
        $this->flushWithdrawalsCache($request->user()->id);

        return $this->success($withdrawal, 'Withdrawal updated successfully');
    }

    public function deleteWithdrawals(Request $request)
    {
        $request->validate(['id' => 'required|integer|min:1']);
        $withdrawal = app(WithdrawalReservationService::class)->transition($request->user(), (int) $request->id, 'rejected');
        $this->flushWithdrawalsCache($request->user()->id);

        // Retain the financial record; cancellation releases only this reservation.
        return $this->success(['id' => $withdrawal->id, 'deleted' => true]);
    }
}
