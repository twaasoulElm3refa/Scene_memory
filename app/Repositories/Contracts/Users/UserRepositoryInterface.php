<?php

namespace App\Repositories\Contracts\Users;

use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;

interface UserRepositoryInterface
{
    public function latestActive(int $limit);
    public function latestPaginated(int $perPage);
    public function findById(int $id): ?User;
    public function create(array $data): User;
    public function latest(int $limit);
    public function count(): int;
    public function countByDate(string $column, Carbon $date): int;
    public function countByCountryName(string $countryName): int;
    public function chunkForNotification(int $size, Closure $callback): void;
    public function firstOrCreateByEmail(string $email, array $attributes): User;
}
