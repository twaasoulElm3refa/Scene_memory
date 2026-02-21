<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsMediaRequest;
use App\Http\Requests\EventsRequest;
use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class UserDashboardController extends Controller
{
    use ApiResponse;

    private $cacheTime = 1 * 3600 * 24;

    public function myEvents()
    {
        $userId = auth()->id();
        $cacheKey = 'my_events_user_id_'.$userId;

        $events = Cache::remember($cacheKey, $this->cacheTime, function () use ($userId) {
            return Events::with('city:id,name', 'sub_categorey:id,name')->where('is_active', 1)
                ->where('user_id', $userId)
                ->withCount('images')
                ->orderBy('created_at', 'desc')
                ->select([
                    'id', 'user_id', 'title', 'slug',
                    'start_date', 'image',
                    'city_id', 'sub_categorey_id',
                ])
                ->get();
        });
        $totalImages = $events->sum('images_count');
        $count = Events::where('user_id', $userId)->count();

        return $this->success([
            'events' => $events,
            'count' => $count,
            'totalImages' => $totalImages,
        ], 'My events');
    }

    public function addMedia(EventsMediaRequest $request, $slug)
    {
        $validated = $request->validated();

        $event = Events::where('slug', $slug)->firstOrFail();

        $createdMedia = [];

        if ($request->hasFile('url')) {
            foreach ($request->file('url') as $file) {
                $path = $file->store('EventMedia', 'public');
                $media = eventsImges::create([
                    'event_id' => $event->id,
                    'url' => $path,

                ]);
                $createdMedia[] = $media;
            }
        }

        $this->clearCache($event->user_id, $event->slug);

        return $this->success($createdMedia, 'تم إضافة الوسائط بنجاح');
    }

    public function create(EventsRequest $request): JsonResponse
    {
        $data = $request->validated();
        try {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5).'-'.time();
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $data['image'] = $image->store('events', 'public');
            }
            $data['user_id'] = auth()->user()->id;
            $event = Events::create($data);
            $this->clearEventsCache();
            $this->clearCache($event->user_id);

            return $this->success($event, 'Event Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function delete()
    {
        try {
            $event = Events::findOrFail(request('id'));
            $this->clearCache($event->user_id, $event->slug);
            $this->clearEventsCache();
            $event->delete();

            return $this->success($event, 'Event Deleted Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();
        try {
            $event = Events::where('slug', request('slug'))->first();
            $oldSlug = $event->slug;
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5).'-'.time();

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $data['image'] = $image->store('events', 'public');
            }
            Cache::forget("events_single_{$oldSlug}");
            $this->clearCache($event->user_id, $event->slug);
            $event->update($data);

            return $this->success($event, 'Event Updated Successfully');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function clearCache($userId = null, $slug = null)
    {
        $cacheKey = 'my_events_user_id_'.$userId;
        Cache::forget($cacheKey);
        Cache::forget("events_single_{$slug}");
        Cache::flush();
    }

    private function clearEventsCache($slug = null)
    {
        $perPage = 8;
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("events_page_{$page}_per_{$perPage}");
        }
        Cache::forget("events_single_{$slug}");
        Cache::forget('events_count');
        Cache::forget('memories');
    }
}
