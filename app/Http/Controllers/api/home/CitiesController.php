<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cities;
use Illuminate\Support\Facades\Cache;

class CitiesController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function index()
    {
        $cacheKey = 'brands_index';

        $cities = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Cities::get(['id', 'name','country_id']);
        });

        if ($cities->isEmpty()) {
            return $this->error('No More cities', 404);
        }

        return $this->success($cities, 'All cities');
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
}
