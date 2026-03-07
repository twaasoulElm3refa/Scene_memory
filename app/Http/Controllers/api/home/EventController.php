<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cities;
use App\Models\Events;
use App\Models\eventsImges;
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

        $events = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            $events = Events::with(['city.translation', 'sub_categorey.translation', 'translation', 'firstImage:id,event_id,url'])->where('is_active', 1)
                ->select('id', 'slug', 'title', 'start_date', 'city_id', 'sub_categorey_id')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $events;
        });

        return $this->success($events, 'All events');
    }

    public function index(Request $request)
    {
        $cityId = $request->city_id;
        $categoryId = $request->sub_category_id;
        $from = $request->query('from');
        $to = $request->query('to');
        $cacheKey = 'events_from_marker_'.
            app()->getLocale().'_'.
            $cityId.'_'.
            $categoryId.'_'.
            $from.'_'.
            $to;

        $events = Cache::remember($cacheKey, $this->cacheTime, function () use ($cityId, $categoryId, $from, $to) {
            return Events::with('city.translation', 'sub_categorey.translation', 'translation')
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
        $events = Cache::remember($cacheKey, now()->addHours(6), function () use ($city) {
            $DBCITY = Cities::query()
                ->where('name', 'LIKE', "%{$city}%")
                ->first();
            if (! $DBCITY) {
                return null;
            }
            return $DBCITY->events()
                ->with('city.translation', 'sub_categorey.translation', 'translation')
                ->select('id', 'slug', 'title', 'image', 'start_date', 'sub_categorey_id', 'city_id')
                ->where('is_active', 1)
                ->latest()
                ->get()
                ->map(function ($event) {
                    $event->image_url = $event->image
                        ? asset('storage/'.ltrim($event->image, '/'))
                        : null;

                    return $event;
                });
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

        $cacheKey = "events_single_{$slug}_".app()->getLocale();
        $cacheTime = now()->addHours(6);

        $event = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return Events::with([
                'city.translation',
                'sub_categorey.translation',
                'user:id,name',
                'images' => fn($q) => $q->where('is_active', 1),
                'translation',

                'comments' => fn ($q) => $q->latest('created_at')
                    ->take(3)
                    ->with('user:id,name','translation'),
            ])->withCount('comments')->withCount('likes')
                ->where('slug', $slug)
                ->first();
        });

        if (! $event) {
            return $this->error('Event not found', 404);
        }

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
        $count = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Events::count();
        });

        return $this->success($count, 'Events count');
    }

    public function memories()
    {
        $cacheKey = 'memories';
        $memories = Cache::remember($cacheKey, $this->cacheTime, function () {
            return eventsImges::count();
        });

        return $this->success($memories, 'Memories');

    }

    public function getImageAttribute($value)
    {
        return $value ?: 'https://via.placeholder.com/300x200?text=Event+Image';
    }
}
