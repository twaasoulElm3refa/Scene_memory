<?php

namespace App\Repositories\Eloquent\Countries;

use App\Models\Countries;
use App\Repositories\Contracts\Countries\CountryRepositoryInterface;

class CountryRepository implements CountryRepositoryInterface
{
    public function allWithTranslation()
    {
        return Countries::with('translation')->get(['id', 'code', 'image']);
    }

    public function allForGate()
    {
        return Countries::select('id', 'code', 'name')->with(['translation:id,country_id,name,locale'])->get();
    }

    public function allBasic()
    {
        return Countries::get(['id', 'name', 'code', 'image']);
    }

    public function paginatedWithTranslation(int $perPage)
    {
        return Countries::with('translation')->paginate($perPage);
    }

    public function findWithCitiesTranslation(int $id)
    {
        return Countries::with(['cities.translation'])->find($id);
    }

    public function findOrFail(int $id)
    {
        return Countries::findOrFail($id);
    }

    public function count(): int
    {
        return Countries::count();
    }

    public function create(array $data)
    {
        return Countries::create($data);
    }

    public function findByCode(string $code)
    {
        return Countries::select('id', 'code', 'name')->with(['translation:id,country_id,name,locale', 'cities.translation:id,city_id,name,locale'])->withCount('cities')->where('code', $code)->first();
    }
}
