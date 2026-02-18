<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EventImageController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function allPerEvent()
    {
        $cacheKey = 'event_image'.$this->cacheTime;
        $event = Cache::remember($cacheKey, $this->cacheTime, function () {
            return eventsImges::where('event_id', request('id'))->get();
        });

        return $this->success($event, 'Event data');
    }

    public function create(Request $request)
    {
        $data = $request->all();
        try {
            if ($request->hasFile('url')) {
                $image = $request->file('url');
                $data['url'] = $image->store('eventImages', 'public');
            }
            if ($request->hasFile('video')) {
                $image = $request->file('video');
                $data['video'] = $image->store('eventVideos', 'public');
            }
            $data['event_id'] = request('id');
            $event = eventsImges::create($data);
            $slug = Events::find(request('id'));
            $data['slug'] = $slug->slug;
            $this->clearCache($data['slug']);

            return $this->success($event, 'Event Created Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

    }

    public function delete()
    {
        $event = eventsImges::findOrFail(request('id'));
        $slug = Events::find($event->event_id);
        $theSlug= $slug->slug;
        $this->clearCache($theSlug);
        $event->delete();
        $this->clearCache();

        return $this->success($event, 'Data Deleted Successfully');
    }

    private function clearCache($slug = '')
    {
        Cache::forget('event_image'.$this->cacheTime);
        cache::forget("events_single_{$slug}");
        Cache::flush();
    }
}
