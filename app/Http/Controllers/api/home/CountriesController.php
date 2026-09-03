<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use App\Repositories\Contracts\Countries\CountryRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Users\UserRepositoryInterface;
use App\Services\LocationCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CountriesController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(
        private readonly CountryRepositoryInterface $countryRepository,
        private readonly CityRepositoryInterface $cityRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LocationCacheService $locationCache
    ) {
    }

    public function index()
    {
        $cacheKey = 'countries_index_'.app()->getLocale();

        $countries = Cache::tags(['countries'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->countryRepository->allWithTranslation();
        });

        if ($countries->isEmpty()) {
            return $this->error('No More countries', 404);
        }

        return $this->success($countries, 'All countries');
    }

    public function all()
    {
        $cacheKey = 'countries_index_all';

        $countries = Cache::tags(['countries'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->countryRepository->allBasic();
        });

        if ($countries->isEmpty()) {
            return $this->error('No More countries', 404);
        }

        return $this->success($countries, 'All countries');
    }

    public function paginated()
    {
        $page = request('page', 1);
        $cacheKey = "countries_index_page_{$page}";

        $countries = Cache::tags(['countries'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->countryRepository->paginatedWithTranslation(15);
        });

        if ($countries->isEmpty()) {
            return $this->error('No More countries', 404);
        }

        return $this->success($countries, 'All countries');
    }

    public function cities()
    {
        $countryId = request('id');
        $cacheKey = "countries_single_{$countryId}_cities_".app()->getLocale();

        $cities = Cache::tags(['countries', 'cities'])->remember($cacheKey, $this->cacheTime, function () use ($countryId) {
            return $this->cityRepository->byCountryId((int) $countryId);
        });

        return $this->success($cities, 'All cities');
    }

    public function single()
    {
        $countryId = request('id');
        $cacheKey = "countries_single_{$countryId}";

        $country = Cache::tags(['countries'])->remember($cacheKey, $this->cacheTime, function () use ($countryId) {
            return $this->countryRepository->findWithCitiesTranslation((int) $countryId);
        });

        if (! $country) {
            return $this->error('No More countries', 404);
        }

        $users = $this->userRepository->countByCountryName($country->name);
        $countryCities = $country->cities->pluck('id');
        $countCities = $countryCities->count();
        $countevents = $this->eventRepository->countByCityIds($countryCities);
        $events = $this->eventRepository->whereInCityIds($countryCities)->paginate(5);

        return $this->success([
            'country' => $country,
            'events' => $events,
            'cities' => $countCities,
            'users' => $users,
            'countevents' => $countevents
        ], 'Country data');
    }

    public function count()
    {
        $cacheKey = 'countries_count';

        $count = Cache::tags(['countries'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->countryRepository->count();
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

            $country = $this->countryRepository->findOrFail((int) $id);
            $country->update($data);

            $this->clearCache($id);

            return $this->success($country, 'Country Updated Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete()
    {
        $country = $this->countryRepository->findOrFail((int) request('id'));
        $country->delete();

        $this->clearCache($country->id);

        return $this->success($country, 'Country Deleted Successfully');
    }

    private function clearCache($id = null)
    {
        $this->locationCache->invalidate();
    }
}
