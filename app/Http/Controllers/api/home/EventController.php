<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cities;
use App\Models\Events;
use App\Models\eventsImges;
use App\Models\EventViews;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EventController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 3600;

    public function all()
    {
        $page = request()->get('page', 1);
        $perPage = 8;

       $cacheKey = "events_page_{$page}_per_{$perPage}_".app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return Events::with(['city.translation', 'sub_categorey.translation', 'translation', 'firstImage:id,event_id,preview_url'])
                ->where('is_active', 1)
                ->select('id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });

        return $this->success($events, 'All events');
    }

    public function historical()
    {
        $page = request()->get('page', 1);
        $perPage = 8;

        $cacheKey = "events_historical_page_{$page}_per_{$perPage}_".app()->getLocale();

        $events = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return Events::with(['city.translation', 'sub_categorey.translation', 'translation', 'firstImage:id,event_id,full_url'])
                ->where('is_active', 1)
                ->where('is_historical', 1)
                ->select('id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });

        return $this->success($events, 'All events');
    }

    public function index(Request $request)
        {
        $cityId = $request->city_id !== 'all' ? $request->city_id : null;
        $categoryId = $request->sub_category_id !== 'all' ? $request->sub_category_id : null;

        $from = $request->query('from');
        $to = $request->query('to');

        $filters = compact('cityId', 'categoryId', 'from', 'to');

        $cacheKey = 'events_' . app()->getLocale() . '_' . md5(json_encode($filters));

        $events = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () use ($cityId, $categoryId, $from, $to) {

            return Events::with('city.translation', 'sub_categorey.translation', 'translation','firstImage:id,event_id,full_url')
                ->where('is_active', 1)
                ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
                ->when($categoryId, fn ($q) => $q->where('sub_categorey_id', $categoryId))
                ->when($from, fn ($q) => $q->whereDate('start_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('end_date', '<=', $to))
                ->orderBy('start_date')
                ->get();
        });

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
            $DBCITY = Cities::query()
                ->where('name', 'LIKE', "%{$city}%")
                ->first();

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
            return Events::with([
                'city.translation',
                'sub_categorey.translation',
                'user:id,name',
                'images' => fn ($q) => $q->where('is_active', 1),
                'translation',
                'comments' => fn ($q) => $q->latest('created_at')
                    ->take(5)->withCount([
                        'interactions as support_count' => fn ($q) => $q->where('type', 'support'),
                        'interactions as exhibitions_count' => fn ($q) => $q->where('type', 'Exhibitions'),
                        'interactions as neutral_count' => fn ($q) => $q->where('type', 'neutral'),
                    ])
                    ->with('user:id,name', 'translation', 'replies', 'replies.user:id,name'),
            ])
            ->withCount('comments')
            ->withCount('likes')
            ->withCount('views')
            ->where('slug', $slug)
            ->first();
        });
        if (! $event) {
            return $this->error('Event not found', 404);
        }
        EventViews::firstOrCreate([
            'event_id' => $event->id,
            'ip_address' => request()->getClientIp(),
        ]);
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
            return Events::count();
        });

        return $this->success($count, 'Events count');
    }

    public function memories()
    {
       $cacheKey = 'memories';

        $memories = Cache::tags(['events'])->remember($cacheKey, $this->cacheTime, function () {
            return eventsImges::count();
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
            return Events::with('translation:event_id,title,id')
                ->where('is_active', 1)
                ->where(function ($query) use ($today) {
                    $query->whereDate('start_date', $today)
                        ->orWhereDate('created_at', $today);
                })
                ->select('id', 'slug', 'title', 'start_date', 'end_date', 'langitude', 'lattitude')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        return $this->success($daily, 'Daily events');
    }

    private function eventCacheKey(string $slug): string
    {
        return 'event_' . strtolower(trim($slug)) . '_' . app()->getLocale();
    }
}
