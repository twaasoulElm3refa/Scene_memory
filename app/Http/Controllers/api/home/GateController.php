<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cities;
use App\Models\Countries;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class GateController extends Controller
{
    use ApiResponse;


    public function random()
    {
        $events = Cache::remember('random_events', 60*60, function () {
            return Events::with('translation','city.translation','sub_categorey.translation','firstImage:id,full_url,event_id')
                ->inRandomOrder()->where('is_active', 1)
                ->take(8)
                ->get();
        });

        return $this->success($events, 'Get Random Events');
    }

    public function countries()
    {
        $countries = Cache::remember('countries_'.app()->getLocale(), now()->addHours(24), function () {
            return Countries::select('id', 'code', 'name')
                ->with([
                    'translation:id,country_id,name,locale',
                ])
                ->get();
        });

        return $this->success($countries, 'Get all Countries');
    }

    public function country($code, Request $request)
    {
        $page    = $request->get('page', 1);
        $locale  = app()->getLocale();
        $countryKey = "country:{$code}:{$locale}";
        $citiesKey  = "country:{$code}:cities";
        $eventsKey  = "country:{$code}:{$locale}:events:page:{$page}";
        $country = Cache::remember($countryKey, now()->addHours(24), function () use ($code) {
            return Countries::select('id', 'code', 'name')
                ->with([
                    'translation:id,country_id,name,locale',
                    'cities.translation:id,city_id,name,locale',
                ])->withcount('cities')
                ->where('code', $code)
                ->first();
        });
        if (!$country) {
            return $this->error('Country not found', 404);
        }
        $cityIds = Cache::remember($citiesKey, now()->addHours(24), function () use ($country) {
            return Cities::where('country_id', $country->id)
                ->pluck('id');
        });
        $events = Cache::remember($eventsKey, now()->addHours(6), function () use ($cityIds,) {
            return Events::select('id', 'city_id', 'sub_categorey_id', 'start_date', 'slug')
                ->with([
                    'translation:id,event_id,title,description,locale',
                    'city:id,country_id',
                    'city.translation:id,city_id,name,locale',
                    'sub_categorey:id',
                    'sub_categorey.translation:id,category_id,name,locale',
                    'firstImage:id,full_url,event_id',
                ])
                ->whereIn('city_id', $cityIds)
                ->get();
        });
        return $this->success([
            'country' => $country,
            'events'  => $events
        ], 'Get Country with Events');
    }
}
