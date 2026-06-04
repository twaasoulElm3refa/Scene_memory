<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EventController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 3600;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CityRepositoryInterface $cityRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {
    }

    public function all()
    {
        $page = request()->get('page', 1);
        $perPage = 8;

        $cacheKey = "events_page_{$page}_per_{$perPage}_".app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return $this->eventRepository->allActivePaginated($perPage);
        });

        return $this->success($events, 'All events');
    }

    public function historical()
    {
        $page = request()->get('page', 1);
        $perPage = 8;

        $cacheKey = "events_historical_page_{$page}_per_{$perPage}_".app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return $this->eventRepository->historicalActivePaginated($perPage);
        });

        return $this->success($events, 'All events');
    }

    public function index(Request $request)
    {
        $cityId = $request->city_id !== 'all' ? $request->city_id : null;
        $categoryId = $request->sub_category_id !== 'all' ? $request->sub_category_id : null;

        $from = $request->query('from');
        $to = $request->query('to');

        $q = $request->query('q', '*');
        $perPage = $request->query('per_page', 15);

        $filters = [
            'is_active:=true',
        ];

        if ($cityId) {
            $filters[] = 'city_id:=' . (int) $cityId;
        }

        if ($categoryId) {
            $filters[] = 'sub_category_id:=' . (int) $categoryId;
        }

        if ($from) {
            $filters[] = 'start_date:>=' . Carbon::parse($from)->timestamp;
        }

        if ($to) {
            $filters[] = 'end_date:<=' . Carbon::parse($to)->timestamp;
        }
        $events = $this->eventRepository->filteredActive($filters);

        return $this->success($events, 'Events');
    }

    public function MarkerSearch()
    {
        $city = request('city');

        if (! $city) {
            return $this->error('city not found', 404);
        }
        $city = str_replace(['منطقة', 'مدينة', 'محافظة'], '', $city);
        $city = trim($city);
        $cacheKey = 'city_events_'.strtolower($city).'_'.app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, now()->addHours(6), function () use ($city) {
            $DBCITY = $this->cityRepository->firstByNameLike($city);

            if (! $DBCITY) {
                return null;
            }

            return $DBCITY->events()
                ->with('city.translation', 'sub_categorey.translation', 'translation','firstImage:id,event_id,full_url')
                ->select('id', 'slug', 'title', 'image', 'start_date', 'sub_categorey_id', 'city_id','langitude','lattitude')
                ->where('is_active', 1)
                ->latest()
                ->get();
        });

        if (! $events) {
            return $this->error('City not found in DB', 404);
        }

        return $this->success($events, 'City is found');
    }

    public function single()
    {
        $slug = request('slug');
        if (! $slug || ! is_string($slug)) {
            return $this->error('Invalid slug', 400);
        }

        $cacheKey = $this->eventCacheKey($slug);

            $event = Cache::tags(['events'])->remember($cacheKey, now()->addHours(6), function () use ($slug) {
            return $this->eventRepository->findSingleDetailedBySlug($slug);
        });
        if (! $event) {
            return $this->error('Event not found', 404);
        }
        $this->eventRepository->firstOrCreateView($event->id, (string) request()->getClientIp());
        if ($event->images && $event->images->isNotEmpty()) {
            foreach ($event->images as $image) {
                if ($image->url) {
                    $image->url = \Storage::url($image->url) ?: null;
                }
            }
        }

        return $this->success($event, 'Event data');
    }

    public function count()
    {
        $cacheKey = 'events_count';

        $count = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->eventRepository->count();
        });

        return $this->success($count, 'Events count');
    }

    public function memories()
    {
        $cacheKey = 'memories';

        $memories = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->eventImageRepository->count();
        });

        return $this->success($memories, 'Memories');

    }

    public function getImageAttribute($value)
    {
        return $value ?: 'https://via.placeholder.com/300x200?text=Event+Image';
    }

    public function daily()
    {
        $cacheKey = 'daily_events_'.app()->getLocale();
        $today = Carbon::today();

        $daily = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($today) {
            return $this->eventRepository->daily($today);
        });

        return $this->success($daily, 'Daily events');
    }

    private function eventCacheKey(string $slug): string
    {
        return 'event_' . strtolower(trim($slug)) . '_' . app()->getLocale();
    }
}
