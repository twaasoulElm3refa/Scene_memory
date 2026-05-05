<?php

namespace App\Repositories\Eloquent\Users;

use App\Models\User;
use App\Repositories\Contracts\Users\UserRepositoryInterface;
use Closure;
use Illuminate\Support\Carbon;

class UserRepository implements UserRepositoryInterface
{
    public function latestActive(int $limit)
    {
        return User::latest()->where('is_active', 1)->take($limit)->get();
    }

    public function latestPaginated(int $perPage)
    {
        return User::latest()->paginate($perPage);
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function latest(int $limit)
    {
        return User::select(['id', 'name', 'email', 'created_at', 'role'])->latest()->limit($limit)->get();
    }

    public function count(): int
    {
        return User::count();
    }

    public function countByDate(string $column, Carbon $date): int
    {
        return User::whereDate($column, $date)->orderBy('id', 'desc')->count();
    }

    public function countByCountryName(string $countryName): int
    {
        return User::where('country', $countryName)->count();
    }

    public function chunkForNotification(int $size, Closure $callback): void
    {
        User::select('id', 'email')->chunk($size, $callback);
    }

    public function firstOrCreateByEmail(string $email, array $attributes): User
    {
        return User::firstOrCreate(['email' => $email], $attributes);
    }
}
