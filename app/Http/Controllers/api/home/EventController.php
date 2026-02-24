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

        $cacheKey = "events_page_{$page}_per_{$perPage}";

        $events = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            $events = Events::with(['city:id,name', 'sub_categorey:id,name'])->where('is_active', 1)
                ->select('id', 'slug', 'title', 'image', 'start_date', 'city_id', 'sub_categorey_id')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $events->getCollection()->transform(function ($event) {
                if (! $event->image) {
                    $event->image = 'https://via.placeholder.com/300x200?text=Event+Image';
                }

                return $event;
            });

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

        $cacheKey = 'events_'.md5(json_encode([
            'city' => $cityId,
            'sub_categorey_id   ' => $categoryId,
            'from' => $from,
            'to' => $to,
        ]));

        $events = Cache::remember($cacheKey, $this->cacheTime, function () use ($cityId, $categoryId, $from, $to) {
            return Events::with('city')->where('is_active', 1)
                ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
                ->when($categoryId, fn ($q) => $q->where('sub_categorey_id', $categoryId))
                ->when($from, fn ($q) => $q->whereDate('start_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('end_date', '<=', $to))
                ->orderBy('start_date')
                ->get(['id', 'title', 'description', 'start_date', 'end_date', 'city_id']);
        });

        return $this->success($events, 'Events');
    }

    public function MarkerSearch()
    {
        $city = request('city');

        if (! $city) {
            return $this->error('city not found', 404);
        }

        $cacheKey = 'city_events_'.strtolower($city);

        $events = Cache::remember($cacheKey, now()->addHours(6), function () use ($city) {
            $DBCITY = Cities::query()
                ->where('name', $city)
                ->first();

            if (! $DBCITY) {
                return null;
            }

            return $DBCITY->events()
                ->with('city')
                ->select('slug', 'title', 'image', 'start_date', 'city_id', 'langitude', 'lattitude')
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

        $cacheKey = "events_single_{$slug}";
        $cacheTime = now()->addHours(6);

        $event = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return Events::with([
                'city:id,name',
                'sub_categorey:id,name',
                'user:id,name',
                'images',

                'comments' => fn ($q) => $q->latest('created_at')
                    ->take(3)
                    ->with('user:id,name'),
            ])->withCount('comments')
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
