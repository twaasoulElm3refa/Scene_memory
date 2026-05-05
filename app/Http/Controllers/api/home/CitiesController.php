<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use App\Repositories\Contracts\Countries\CountryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CitiesController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(
        private readonly CityRepositoryInterface $cityRepository,
        private readonly CountryRepositoryInterface $countryRepository
    ) {
    }

    public function index()
    {
        $cacheKey = 'cities_index_paginated_'.app()->getLocale();

        $cities = Cache::tags(['cities'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->cityRepository->paginatedWithTranslation(10);
        });

        if ($cities->isEmpty()) {
            return $this->error('No More cities', 404);
        }

        return $this->success($cities, 'All cities');
    }

    public function paginated()
    {
        $page = request('page', 1);
        $perPage = 5;
        $cacheKey = "cities_paginated_page_{$page}_per_{$perPage}_".app()->getLocale();

        $cities = Cache::tags(['cities'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return $this->cityRepository->paginatedWithRelations($perPage);
        });

        $countCities = Cache::tags(['cities'])->remember('cities_count', $this->cacheTime, function () {
            return $this->cityRepository->count();
        });

        $countCountries = Cache::tags(['cities'])->remember('countries_count', $this->cacheTime, function () {
            return $this->countryRepository->count();
        });

        if ($cities->isEmpty()) {
            return $this->error('No More cities', 404);
        }

        return $this->success([
            'cities' => $cities,
            'count_cities' => $countCities,
            'count_countries' => $countCountries
        ], 'All cities');
    }

    public function single()
    {
        $cityId = request('id');
        $cacheKey = "cities_single_{$cityId}_".app()->getLocale();

        $city = Cache::tags(['cities'])->remember($cacheKey, $this->cacheTime, function () use ($cityId) {
            return $this->cityRepository->findWithEventsAndTranslation((int) $cityId);
        });

        if (! $city) {
            return $this->error('No More cities', 404);
        }

        return $this->success($city, 'City data');
    }

    public function update(Request $request)
    {
        $data = $request->all();
        try {
            $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();
            $city = $this->cityRepository->findOrFail((int) request('id'));
            $city->update($data);

            $this->clearCache();

            return $this->success($city, 'City Updated Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete()
    {
        try {
            $city = $this->cityRepository->findOrFail((int) request('id'));
            $city->delete();

            $this->clearCache();

            return $this->success($city, 'City Deleted Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function clearCache()
    {
        Cache::tags(['cities'])->flush();
    }
}
