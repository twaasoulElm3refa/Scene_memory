<?php

namespace App\Http\Controllers\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Mail\EventRequest;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository,
        private readonly RequestRepositoryInterface $requestRepository
    ) {
    }

    public function create(EventsRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['urls']);
        try {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5).'-'.time();
            $data['is_active'] = 0;
            $data['user_id'] = auth()->user()->id;
            $event = $this->eventRepository->create($data);
            if ($request->hasFile('urls')) {
                foreach ($request->file('urls') as $file) {
                    $path = $file->store('Photos', 'public');
                    $media = $this->eventImageRepository->create([
                        'event_id' => $event->id,
                        'url' => $path,

                    ]);
                }
            }
            $this->requestRepository->createEventRequest([
                'event_id' => $event->id,
                'status' => 'pending',
            ]);
            $this->clearEventsCache();
            Mail::to('m7mdellham77@gmail.com')->send(new EventRequest($event));

            return $this->success($event->load('requests'), 'Event Created Successfully');
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
        Cache::forget('requests_page_1');
        Cache::forget('events_count');
        Cache::forget('memories');
    }
}
