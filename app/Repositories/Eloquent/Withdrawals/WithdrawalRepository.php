<?php

namespace App\Repositories\Eloquent\Withdrawals;

use App\Models\withdraw;
use App\Repositories\Contracts\Withdrawals\WithdrawalRepositoryInterface;

class WithdrawalRepository implements WithdrawalRepositoryInterface
{
    public function paginated(int $perPage = 10)
    {
        return withdraw::with('user.wallet')->paginate($perPage);
    }

    public function count(): int
    {
        return withdraw::count();
    }

    public function paginatedByStatus(string $status, int $perPage = 10)
    {
        return withdraw::query()->select(['id', 'user_id', 'approved_by', 'amount', 'fee', 'status', 'reference', 'processed_at', 'created_at'])->with(['user', 'approvedBy'])->where('status', $status)->paginate($perPage);
    }

    public function find(int $id)
    {
        return withdraw::find($id);
    }

    public function findOrFail(int $id)
    {
        return withdraw::findOrFail($id);
    }
}
