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
        $events = Cache::remember('random_events', 60*60, function () {
            return $this->eventRepository->randomActive(8);
        });

        return $this->success($events, 'Get Random Events');
    }

    public function countries()
    {
        $countries = Cache::remember('countries_'.app()->getLocale(), now()->addHours(24), function () {
            return $this->countryRepository->allForGate();
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
            return $this->countryRepository->findByCode($code);
        });
        if (!$country) {
            return $this->error('Country not found', 404);
        }
        $cityIds = Cache::remember($citiesKey, now()->addHours(24), function () use ($country) {
            return $this->cityRepository->byCountryId((int) $country->id)->pluck('id');
        });
        $events = Cache::remember($eventsKey, now()->addHours(6), function () use ($cityIds,) {
            return $this->eventRepository->gateEventsByCityIds($cityIds);
        });
        return $this->success([
            'country' => $country,
            'events'  => $events
        ], 'Get Country with Events');
    }
}
