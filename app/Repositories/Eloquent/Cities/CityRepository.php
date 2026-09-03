<?php

namespace App\Repositories\Eloquent\Cities;

use App\Models\Cities;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;

class CityRepository implements CityRepositoryInterface
{
    public function paginatedWithTranslation(int $perPage)
    {
        return Cities::with('translation')->paginate($perPage);
    }

    public function paginatedWithRelations(int $perPage)
    {
        return Cities::select('id', 'name', 'country_id')->with('countries:id,name', 'translation')->withCount('events')->paginate($perPage);
    }

    public function findWithEventsAndTranslation(int $id)
    {
        return Cities::with('events', 'translation')->find($id);
    }

    public function findOrFail(int $id)
    {
        return Cities::findOrFail($id);
    }

    public function create(array $data)
    {
        return Cities::create($data);
    }

    public function count(): int
    {
        return Cities::count();
    }

    public function byCountryId(int $countryId)
    {
        return Cities::with('translation')->where('country_id', $countryId)->get();
    }

    public function firstByNameLike(string $cityName)
    {
        return Cities::query()->where('name', 'LIKE', "%{$cityName}%")->first();
    }

    public function findByNormalizedName(string $name, bool $lockForUpdate = false)
    {
        $normalizedName = mb_strtolower($name);
        $query = Cities::query()
            ->where(function ($query) use ($normalizedName) {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
                    ->orWhereHas('translations', function ($translationQuery) use ($normalizedName) {
                        $translationQuery->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName]);
                    });
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
