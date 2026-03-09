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
                        $path = $file->store('Photos', 'public');
                        eventsImges::create([
                            'event_id' => $event->id,
                            'url' => $path,
                            'is_active' => 1,
                        ]);
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
