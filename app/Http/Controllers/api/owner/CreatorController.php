<?php

namespace App\Http\Controllers\api\owner;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreatorController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventRepositoryInterface $eventRepository)
    {
    }

    public function all()
    {
        try {
            $userId = auth()->id();
            $locale = app()->getLocale();
            $cacheKey = "events_user_{$userId}_{$locale}";
            $events = Cache::tags(['events', 'user_'.$userId])
                ->remember($cacheKey, now()->addMinutes(10), function () use ($userId) {
                    return $this->eventRepository->creatorEvents($userId);
                });
            return $this->success($events, 'All events');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error($th->getMessage());
        }
    }

    public function show(string $slug)
    {
        try {
            $lang = request()->header('Accept-Language', app()->getLocale());
            $cacheKey = "events:show:{$slug}:lang:{$lang}";
            $event = Cache::tags(['events', "event:{$slug}", "lang:{$lang}"])
                ->remember($cacheKey, now()->addMinutes(30), function () use ($slug) {
                    return $this->eventRepository->show($slug);
                });
            return $this->success($event, 'Event');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error($th->getMessage());
        }
    }

    public function total()
    {

    }

}
