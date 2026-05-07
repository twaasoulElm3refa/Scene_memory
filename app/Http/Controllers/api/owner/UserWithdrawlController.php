<?php

namespace App\Http\Controllers\api\owner;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserWithdrawlController extends Controller
{
    use ApiResponse;

    private function withdrawalsVersionKey(int $userId): string
    {
        return 'user_withdrawals_version_' . $userId;
    }

    private function withdrawalsVersion(int $userId): int
    {
        return (int) Cache::get($this->withdrawalsVersionKey($userId), 1);
    }

    private function withdrawalsCacheKey(int $userId, array $params = []): string
    {
        $version = $this->withdrawalsVersion($userId);

        return 'user_withdrawals_' . $userId . '_v' . $version . '_' . md5(json_encode($params));
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

    private function findUserWithdrawal(int $userId, int $withdrawalId): ?withdraw
    {
        return withdraw::query()
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
            $query = withdraw::query()
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

    public function requestWithdrawals(Request $request, $id)
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }

        $wallet = $user->wallet;

        if (! $wallet) {
            return $this->error('Wallet not found.', 404);
        }

        if ((int) $id !== (int) $wallet->id) {
            return $this->forbidden('You are not authorized to request withdrawal from this wallet.');
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'gt:0'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'method' => ['nullable', 'string', 'max:100'],
            'payment_details' => ['nullable'],
            'reference' => ['nullable', 'string', 'max:191'],
            'transaction_id' => ['nullable', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $amount = (float) $request->input('amount');
        $fee = (float) $request->input('fee', 0);

        if ($fee > $amount) {
            return $this->validationError([
                'fee' => ['Fee cannot be greater than amount.'],
            ]);
        }

        $walletAmount = (float) ($wallet->amount ?? 0);

        if ($amount > $walletAmount) {
            return $this->error('Insufficient wallet balance.', 422);
        }

        $invalidPaymentDetails = false;
        $paymentDetails = $this->normalizePaymentDetails($request->input('payment_details'), $invalidPaymentDetails);

        if ($invalidPaymentDetails) {
            return $this->validationError([
                'payment_details' => ['Payment details must be a valid JSON object or array.'],
            ]);
        }

        try {
            $withdrawal = DB::transaction(function () use ($user, $wallet, $request, $amount, $fee, $paymentDetails) {
                $wallet->decrement('amount', $amount);
                $wallet->increment('pending', $amount);
                return withdraw::query()->create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'fee' => $fee,
                    'net_amount' => $amount - $fee,
                    'currency' => $request->input('currency', $user->wallet?->currency ?? 'EGP'),
                    'status' => 'pending',
                    'method' => $request->input('method'),
                    'payment_details' => $paymentDetails,
                    'reference' => $request->input('reference'),
                    'transaction_id' => $request->input('transaction_id'),
                ])->fresh(['approvedBy:id,name']);
            });
            $this->flushWithdrawalsCache($user->id);

            return $this->success($withdrawal, 'Withdrawal request created successfully');
        } catch (\Throwable $exception) {
            Log::error('Create withdrawal request failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Failed to create withdrawal request.');
        }
    }

    public function updateWithdrawals(Request $request, $id)
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized('Unauthenticated.');
        }

        $withdrawal = $this->findUserWithdrawal($user->id, (int) $id);

        if (! $withdrawal) {
            return $this->notFound('Withdrawal not found');
        }

        if (! in_array($withdrawal->status, ['pending'], true)) {
            return $this->error('Only pending withdrawals can be updated.', 422);
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'fee' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'method' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payment_details' => ['sometimes', 'nullable'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'status' => ['sometimes', Rule::in(['pending'])],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $fillableFields = [
            'amount',
            'fee',
            'currency',
            'method',
            'payment_details',
            'reference',
            'transaction_id',
        ];

        $payload = [];

        foreach ($fillableFields as $field) {
            if ($request->exists($field)) {
                $payload[$field] = $request->input($field);
            }
        }

        if (empty($payload)) {
            return $this->validationError([
                'data' => ['No valid fields were provided for update.'],
            ]);
        }

        $amount = array_key_exists('amount', $payload)
            ? (float) $payload['amount']
            : (float) $withdrawal->amount;

        $fee = array_key_exists('fee', $payload)
            ? (float) $payload['fee']
            : (float) $withdrawal->fee;

        if ($fee > $amount) {
            return $this->validationError([
                'fee' => ['Fee cannot be greater than amount.'],
            ]);
        }

        $walletAmount = (float) ($user->wallet?->amount ?? 0);

        if ($amount > $walletAmount) {
            return $this->error('Insufficient wallet balance.', 422);
        }

        if (array_key_exists('payment_details', $payload)) {
            $invalidPaymentDetails = false;
            $payload['payment_details'] = $this->normalizePaymentDetails(
                $payload['payment_details'],
                $invalidPaymentDetails
            );

            if ($invalidPaymentDetails) {
                return $this->validationError([
                    'payment_details' => ['Payment details must be a valid JSON object or array.'],
                ]);
            }
        }

        $payload['net_amount'] = $amount - $fee;

        try {
            $withdrawal->update($payload);

            $this->flushWithdrawalsCache($user->id);

            return $this->success(
                $withdrawal->fresh(['approvedBy:id,name']),
                'Withdrawal updated successfully'
            );
        } catch (\Throwable $exception) {
            Log::error('Update withdrawal request failed', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Failed to update withdrawal request.');
        }
    }

    public function deleteWithdrawals(Request $request)
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

        if (in_array($withdrawal->status, ['processing', 'completed'], true)) {
            return $this->error('This withdrawal cannot be deleted after processing.', 422);
        }

        try {
            $withdrawal->delete();

            $this->flushWithdrawalsCache($user->id);

            return $this->success([
                'id' => $withdrawal->id,
                'deleted' => true,
            ], 'Withdrawal deleted successfully');
        } catch (\Throwable $exception) {
            Log::error('Delete withdrawal request failed', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Failed to delete withdrawal request.');
        }
    }
}
