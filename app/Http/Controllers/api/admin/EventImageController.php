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

    /**
     * عرض كل الصور الخاصة بحدث محدد
     */
    public function allPerEvent()
    {
        $eventId = request('id');
        $cacheKey = "event_image_event_{$eventId}";

        $eventImages = Cache::remember($cacheKey, $this->cacheTime, function () use ($eventId) {
            return eventsImges::where('event_id', $eventId)->get();
        });

        return $this->success($eventImages, 'Event images fetched successfully');
    }

    /**
     * إضافة صورة أو فيديو للحدث
     */
    public function create(Request $request)
    {
        try {
            $data = ['event_id' => request('id')];

            if ($request->hasFile('url')) {
                $data['url'] = $request->file('url')->store('eventImages', 'public');
            }

            if ($request->hasFile('video')) {
                $data['video'] = $request->file('video')->store('eventVideos', 'public');
            }

            $eventImage = eventsImges::create($data);

            $event = Events::findOrFail($data['event_id']);
            $this->clearCache($event->id, $event->slug);

            return $this->success($eventImage, 'Event media added successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * حذف صورة أو فيديو
     */
    public function delete()
    {
        try {
            $eventImage = eventsImges::findOrFail(request('id'));
            $event = Events::findOrFail($eventImage->event_id);

            $eventImage->delete();
            $this->clearCache($event->id, $event->slug);

            return $this->success($eventImage, 'Event media deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * مسح الكاش الخاص بالحدث والصور
     */
    private function clearCache($eventId = null, $slug = null)
    {
        // مسح كاش الصور للحدث
        if ($eventId) {
            Cache::forget("event_image_event_{$eventId}");
        }

        // مسح كاش single event لكل اللغات
        if ($slug) {
            $locales = ['ar','en','fr','es','zh','de','ru','it','ja','fa','ur','hi'];
            foreach ($locales as $locale) {
                Cache::forget("events_single_{$slug}_{$locale}");
            }
        }
    }
}
