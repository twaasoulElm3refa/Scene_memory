<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use App\Repositories\Contracts\Countries\CountryRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class GateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CountryRepositoryInterface $countryRepository,
        private readonly CityRepositoryInterface $cityRepository
    ) {
    }


    public function random()
    {
        $locale = app()->getLocale();
        $take = 8;
        $events = Cache::remember("gate:random:locale:{$locale}:take:{$take}", now()->addHour(), function () use ($take) {
            return $this->eventRepository->randomActive($take);
        });

        return $this->success($events, 'Get Random Events')
            ->header('Vary', 'Accept-Language');
    }

    public function countries()
    {
        $locale = app()->getLocale();
        $countries = Cache::tags(['countries'])->remember("gate:countries:locale:{$locale}", now()->addHours(24), function () {
            return $this->countryRepository->allForGate();
        });

        return $this->success($countries, 'Get all Countries')
            ->header('Vary', 'Accept-Language');
    }

    public function country($code, Request $request)
    {
        $code = strtoupper(trim($code));
        $page = max(1, (int) $request->get('page', 1));
        $locale = app()->getLocale();
        $countryKey = "gate:country:{$code}:locale:{$locale}";
        $citiesKey = "gate:country:{$code}:cities";
        $eventsKey = "gate:country:{$code}:locale:{$locale}:events:page:{$page}";
        $country = Cache::tags(['countries'])->remember($countryKey, now()->addHours(24), function () use ($code) {
            return $this->countryRepository->findByCode($code);
        });
        if (!$country) {
            return $this->error('Country not found', 404)
                ->header('Vary', 'Accept-Language');
        }
        $cityIds = Cache::tags(['countries', 'cities'])->remember($citiesKey, now()->addHours(24), function () use ($country) {
            return $this->cityRepository->byCountryId((int) $country->id)->pluck('id');
        });
        $events = Cache::tags(['countries', 'cities', 'events'])->remember($eventsKey, now()->addHours(6), function () use ($cityIds) {
            return $this->eventRepository->gateEventsByCityIds($cityIds);
        });

        return $this->success([
            'country' => $country,
            'events'  => $events
        ], 'Get Country with Events')
            ->header('Vary', 'Accept-Language');
    }
}
