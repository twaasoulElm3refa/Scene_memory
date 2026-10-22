<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsMediaRequest;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Rules\SafeMedia;
use App\Services\EventMediaIngest;
use App\Support\EventAccess;
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
        EventAccess::authorize($event, $request->user());

        $createdMedia = app(EventMediaIngest::class)->upload($event, $request->file('url', []));
        $this->clearCache($event->user_id, $event->slug);

        return $this->success($createdMedia, 'تم إضافة الوسائط بنجاح');
    }

    public function delete()
    {
        $event = $this->eventRepository->findById((int) request()->route('id'));
        EventAccess::authorize($event, request()->user());
        try {
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
        $event = $this->eventRepository->findBySlug((string) $request->route('slug'));
        EventAccess::authorize($event, $request->user());
        $data = $request->validate([
            'title' => 'required|string|max:255', 'description' => 'sometimes|string|max:10000',
            'city_id' => 'sometimes|nullable|exists:cities,id',
            'sub_categorey_id' => 'sometimes|nullable|exists:sub_categoreys,id',
            'start_date' => 'sometimes|nullable|date', 'end_date' => 'sometimes|nullable|date',
            'time' => 'sometimes|nullable|string|max:30',
            'lattitude' => 'sometimes|nullable|numeric|between:-90,90',
            'langitude' => 'sometimes|nullable|numeric|between:-180,180',
            'image' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', new SafeMedia],
        ]);
        try {
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
