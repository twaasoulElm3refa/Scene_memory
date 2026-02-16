<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Models\Cities;
use App\Models\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CitiesController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function index()
    {
        $cacheKey = 'cities_index';

        $cities = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Cities::get(['id', 'name', 'country_id']);
        });

        if ($cities->isEmpty()) {
            return $this->error('No More cities', 404);
        }

        return $this->success($cities, 'All cities');
    }

    public function paginated()
    {
        $page = request('page', 1);
        $cacheKey = 'cities_index_page_'.$page;

        $cities = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Cities::select('id', 'name', 'country_id')
                ->with('countries:id,name')
                ->withCount('events')
                ->paginate(5);
        });
        $countCities = Cities::count();
        $countCountries = Countries::count();
        if ($cities->isEmpty()) {
            return $this->error('No More cities', 404);
        }

        return $this->success(['cities' => $cities, 'count_cities' => $countCities, 'count_countries' => $countCountries], 'All cities');
    }

    public function single()
    {
        $cityId = request('id');
        $cacheKey = "cities_single_{$cityId}";

        $cities = Cache::remember($cacheKey, $this->cacheTime, function () use ($cityId) {
            return Cities::with('events')->find($cityId);
        });
        if (! $cities) {
            return $this->error('No More cities', 404);
        }
        return $this->success($cities, 'City data');
    }

    public function create(CityRequest $request)
    {
        $data = $request->validated();
        try {
            $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();
            $city = Cities::create($data);
            Cache::forget('cities_count');
            Cache::forget('cities_index_page_1');
            Cache::flush();

            return $this->success($city, 'City Created Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update(Request $request)
    {
        $data = $request->all();
        try {
            $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();
            $city = Cities::findOrFail(request('id'));
            $city->update($data);
            Cache::forget('cities_count');
            Cache::forget('cities_index_page_1');
            Cache::flush();

            return $this->success($city, 'City Updated Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete()
    {
        try {
            $city = Cities::findOrFail(request('id'));
            $city->delete();
            Cache::forget('cities_count');
            Cache::forget('cities_index_page_1');
            Cache::flush();

            return $this->success($city, 'City Deleted Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
