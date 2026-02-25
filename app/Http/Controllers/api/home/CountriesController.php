<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Countries;
use App\Models\Events;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CountriesController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function index()
    {
        $cacheKey = 'countries_index';
        $countries = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Countries::with('translation')->get('id','code','image');
        });
        if ($countries->count() == 0) {
            return $this->error('No More countries', 404);
        }

        return $this->success($countries, 'All countries');
    }

    public function paginated()
    {
        $page = request('page', 1);
        $cacheKey = "countries_index_page_{$page}";
        $countries = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Countries::with("translation")->paginate(5);
        });
        if ($countries->count() == 0) {
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
        $users = User::where('country', $countries->name)->count();
        $countryCities = $countries->cities->pluck('id');
        $countCities = count($countries->cities);
        $countevents = Events::whereIn('city_id', $countryCities)->count();
        $events = Events::whereIn('city_id', $countryCities)->paginate(5);

        return $this->success(['countries' => $countries, 'events' => $events, 'cities' => $countCities, 'users' => $users, 'countevents' => $countevents], 'category');
    }

    public function count()
    {
        $cacheKey = 'countries_count';
        $count = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Countries::count();
        });

        return $this->success($count, 'Countries count');
    }

   

    public function update(Request $request, $id)
    {
        $data = $request->only(['name', 'image']);
        try {
            $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('countries', 'public');
            }
            $country = Countries::findOrFail($id);
            $country->update($data);
            Cache::forget("countries_single_{$id}");
            Cache::forget('countries_count');
            Cache::flush();

            return $this->success($country, 'Country Updated Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete()
    {
        $country = Countries::findOrFail(request('id'));
        $country->delete();
        Cache::forget("countries_single_{$country->id}");
        Cache::forget('countries_count');
        Cache::flush();

        return $this->success($country, 'Country Deleted Successfully');
    }
}
