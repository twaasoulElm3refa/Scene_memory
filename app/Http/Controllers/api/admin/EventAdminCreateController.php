<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Jobs\TranslateEventJob;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventAdminCreateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {
    }

    public function create(EventsRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['urls']);

        try {
            $event = DB::transaction(function () use ($data, $request) {
                $data['slug'] = Str::slug($data['title'])
                                .'-'.Str::random(5)
                                .'-'.time();
                $data['user_id'] = auth()->id();
                $event = $this->eventRepository->create($data);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls') as $file) {
                        $path = $file->store('Photos', 'public');
                        $this->eventImageRepository->create([
                            'event_id' => $event->id,
                            'url' => $path,
                        ]);
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch(
                $event->id,
                $data['title'],
                $data['description'],
                app()->getLocale()
            );

            $this->clearEventsCache($event->slug);

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function historic(EventsRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['urls']);

        try {
            $event = DB::transaction(function () use ($data, $request) {
                $data['slug'] = Str::slug($data['title'])
                                .'-'.Str::random(5)
                                .'-'.time();
                $data['user_id'] = auth()->id();
                $data['is_historical'] = 1;
                $event = $this->eventRepository->create($data);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls') as $file) {
                        $path = $file->store('Photos', 'public');
                        $this->eventImageRepository->create([
                            'event_id' => $event->id,
                            'url' => $path,
                        ]);
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch(
                $event->id,
                $data['title'],
                $data['description'],
                app()->getLocale()
            );

            $this->clearEventsCache($event->slug);

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * مسح كل كاشات الأحداث بعد إنشاء أي حدث
     */
    private function clearEventsCache($slug = null)
    {
        $perPage = 8;

        // مسح صفحات pagination
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("events_page_{$page}_per_{$perPage}");
        }

        // مسح single event لكل اللغات
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        foreach ($locales as $locale) {
            Cache::forget("events_single_{$slug}_{$locale}");
        }

        // مسح العدادات والذاكرة
        Cache::forget('events_count');
        Cache::forget('memories');
    }
}
