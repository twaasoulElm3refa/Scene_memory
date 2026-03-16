<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Jobs\ProcessEventVideoJob;
use App\Jobs\TranslateEventJob;
use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventUserCreateController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

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
                $data['is_active'] = 0;

                $event = Events::create($data);

                EventRequestCreate::create([
                    'event_id' => $event->id,
                ]);

                $event->translations()->create([
                    'locale' => 'ar',
                    'title' => $data['title'],
                    'description' => $data['description'],
                ]);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls') as $file) {
                        $mime = $file->getMimeType();
                        if (str_starts_with($mime, 'image/')) {
                            $path = $file->store('Photos', 'public');
                            eventsImges::create([
                                'event_id' => $event->id,
                                'url' => $path,
                                'is_active' => 1,
                            ]);
                        } elseif (str_starts_with($mime, 'video/')) {
                            $path = $file->store('videos_temp', 'public');
                            ProcessEventVideoJob::dispatch($event->id, $path);
                        }
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch(
                $event->id,
                $data['title'],
                $data['description']
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
                $data['is_active'] = 0;
                $data['is_historical'] = 1;

                $event = Events::create($data);

                EventRequestCreate::create([
                    'event_id' => $event->id,
                ]);

                $event->translations()->create([
                    'locale' => 'ar',
                    'title' => $data['title'],
                    'description' => $data['description'],
                ]);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls') as $file) {
                        $mime = $file->getMimeType();
                        if (str_starts_with($mime, 'image/')) {
                            $path = $file->store('Photos', 'public');
                            eventsImges::create([
                                'event_id' => $event->id,
                                'url' => $path,
                                'is_active' => 1,
                            ]);
                        } elseif (str_starts_with($mime, 'video/')) {
                            $path = $file->store('videos_temp', 'public');
                            ProcessEventVideoJob::dispatch($event->id, $path);
                        }
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch(
                $event->id,
                $data['title'],
                $data['description']
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
     * Clear event-related cache safely using Redis tags
     */
    private function clearEventsCache($slug = null)
    {
        $perPage = 8;

        // Clear paginated caches
        for ($page = 1; $page <= 10; $page++) {
            Cache::tags(['events'])->forget("events_page_{$page}_per_{$perPage}");
        }

        // Clear single event cache
        if ($slug) {
            $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
            foreach ($locales as $locale) {
                Cache::tags(['events'])->forget("events_single_{$slug}_{$locale}");
            }
        }

        // Clear general counts & memories
        Cache::tags(['events'])->forget('events_count');
        Cache::tags(['events'])->forget('memories');
    }
}
