<?php

namespace App\Repositories\Contracts\Withdrawals;

interface WithdrawalRepositoryInterface
{
    public function paginated(int $perPage = 10);
    public function count(): int;
    public function paginatedByStatus(string $status, int $perPage = 10);
    public function find(int $id);
    public function findOrFail(int $id);
}
