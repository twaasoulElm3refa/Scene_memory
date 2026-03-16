<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EventAdminController extends Controller
{
    use ApiResponse;

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();
        try {
            $event = Events::where('slug', $request->input('id'))->firstOrFail();
            $oldSlug = $event->slug;
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5).'-'.time();

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('events', 'public');
            }

            $event->update($data);

            // مسح الكاش القديم بعد التحديث
            $this->clearEventsCache($oldSlug);

            return $this->success($event, 'Event Updated Successfully');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function destroy(): JsonResponse
    {
        $slug = request('id');
        $event = Events::where('slug', $slug)->firstOrFail();
        $event->delete();

        // مسح كل كاشات هذا الحدث بعد الحذف
        $this->clearEventsCache($slug);

        return $this->success($event, 'Event Deleted Successfully');
    }

    /**
     * مسح كل كاشات الأحداث
     */
    private function clearEventsCache($slug = null)
    {
        $perPage = 8;

        // مسح صفحات pagination
        for ($page = 1; $page <= 10; $page++) {
            Cache::tags(['events'])->forget("events_page_{$page}_per_{$perPage}");
        }

        // مسح الـ single event لكل اللغات
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        foreach ($locales as $locale) {
            Cache::tags(['events'])->forget("events_single_{$slug}_{$locale}");
        }

        // مسح العدادات والذاكرة
        Cache::tags(['events'])->forget('events_count');
        Cache::tags(['events'])->forget('memories');
        Artisan::call('queue:work', [
            '--once' => true,
        ]);
    }
}
