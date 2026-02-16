<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Countries;
use Illuminate\Support\Facades\Cache;

class CountriesController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function index()
    {
        $cacheKey = 'brands_index';
        $countries = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Countries::get(['id', 'name']);
        });
        if ($countries->isEmpty()) {
            return $this->error('No More countries', 404);
        }
        return $this->success($countries, 'All countries');
    }

    public function single()
    {
        $countryId = request('id');
        $cacheKey = "countries_single_{$countryId}";
        $countries = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Countries::with('cities')->find(request('id'));
        });
        if (! $countries) {
            return $this->error('No More countries', 404);
        }
        return $this->success($countries, 'category');
    }

    public function count()
    {
        $cacheKey = 'countries_count';
        $count = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Countries::count();
        });
        return $this->success($count, 'Countries count');
    }
}
