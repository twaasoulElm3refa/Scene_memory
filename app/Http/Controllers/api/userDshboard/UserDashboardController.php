<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsMediaRequest;
use App\Jobs\GenerateEventAiTagsJob;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class UserDashboardController extends Controller
{
    use ApiResponse;

    private $cacheTime = 1 * 3600 * 24;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {}

    public function myEvents()
    {
        $userId = auth()->id();
        $cacheKey = 'my_events_user_id_'.$userId;

        $events = Cache::remember($cacheKey, $this->cacheTime, function () use ($userId) {
            return $this->eventRepository->dashboardEvents($userId);
        });
        $totalImages = $events->sum('images_count');
        $count = $this->eventRepository->countByUserId($userId);

        return $this->success([
            'events' => $events,
            'count' => $count,
            'totalImages' => $totalImages,
        ], 'My events');
    }

    public function addMedia(EventsMediaRequest $request, $slug)
    {
        $validated = $request->validated();

        $event = $this->eventRepository->findBySlugOrFail((string) $slug);

        $createdMedia = [];

        if ($request->hasFile('url')) {
            foreach ($request->file('url') as $file) {
                $path = $file->store('EventMedia', 'public');
                $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
                $media = $this->eventImageRepository->create([
                    'event_id' => $event->id,
                    'preview_url' => $path,
                    'full_url' => $path,
                    'type' => $type,
                    'is_active' => 1,
                ]);
                $createdMedia[] = $media;
            }
        }

        $this->clearCache($event->user_id, $event->slug);

        if ($createdMedia !== []) {
            GenerateEventAiTagsJob::dispatch((int) $event->id);
        }

        return $this->success($createdMedia, 'تم إضافة الوسائط بنجاح');
    }

    public function delete()
    {
        try {
            $event = $this->eventRepository->findByIdOrFail((int) request('id'));
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
            $event = $this->eventRepository->findBySlug((string) request('slug'));
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
