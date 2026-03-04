<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Models\Events;
use App\Models\eventsImges;
use App\Jobs\TranslateEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventAdminCreateController extends Controller
{
    use ApiResponse;

    public function create(EventsRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['urls']);

        try {

            $event = DB::transaction(function () use ($data, $request) {

                $data['slug'] = Str::slug($data['title'])
                                . '-' . Str::random(5)
                                . '-' . time();

                $data['user_id'] = auth()->id();
                $event = Events::create($data);
                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls') as $file) {
                        $path = $file->store('Photos', 'public');

                        eventsImges::create([
                            'event_id' => $event->id,
                            'url'      => $path,
                        ]);
                    }
                }
                return $event;
            });

            TranslateEventJob::dispatch(
                $event->id,
                $data['title'],
                $data['description']
                ,app()->getLocale()
            );

            $this->clearEventsCache($event->slug);

            return $this->success(
                $event->load('translations','photos'),
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
