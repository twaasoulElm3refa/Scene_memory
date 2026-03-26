<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cities;
use App\Models\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CitiesController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600; // 10 دقائق

    /**
     * كل المدن بدون pagination
     */
    public function index()
    {
        $cacheKey = 'cities_index_paginated_'.app()->getLocale();

        $cities = Cache::tags(['cities'])->remember($cacheKey, $this->cacheTime, function () {
            return Cities::with('translation')->paginate(10);
        });

        if ($cities->isEmpty()) {
            return $this->error('No More cities', 404);
        }

        return $this->success($cities, 'All cities');
    }

    /**
     * كل المدن مع pagination
     */
    public function paginated()
    {
        $page = request('page', 1);
        $perPage = 5;
        $cacheKey = "cities_paginated_page_{$page}_per_{$perPage}_".app()->getLocale();

        $cities = Cache::tags(['cities'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return Cities::select('id', 'name', 'country_id')
                ->with('countries:id,name', 'translation')
                ->withCount('events')
                ->paginate($perPage);
        });

        $countCities = Cache::tags(['cities'])->remember('cities_count', $this->cacheTime, function () {
            return Cities::count();
        });

        $countCountries = Cache::tags(['cities'])->remember('countries_count', $this->cacheTime, function () {
            return Countries::count();
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

    /**
     * بيانات مدينة واحدة
     */
    public function single()
    {
        $cityId = request('id');
        $cacheKey = "cities_single_{$cityId}_".app()->getLocale();

        $city = Cache::tags(['cities'])->remember($cacheKey, $this->cacheTime, function () use ($cityId) {
            return Cities::with('events', 'translation')->find($cityId);
        });

        if (! $city) {
            return $this->error('No More cities', 404);
        }

        return $this->success($city, 'City data');
    }

    /**
     * تحديث مدينة
     */
    public function update(Request $request)
    {
        $data = $request->all();
        try {
            $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();
            $city = Cities::findOrFail(request('id'));
            $city->update($data);

            $this->clearCache();

            return $this->success($city, 'City Updated Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * حذف مدينة
     */
    public function delete()
    {
        try {
            $city = Cities::findOrFail(request('id'));
            $city->delete();

            $this->clearCache();

            return $this->success($city, 'City Deleted Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * مسح كل الكاش المتعلق بالمدن
     */
    private function clearCache()
    {
        Cache::tags(['cities'])->flush();
    }
}
