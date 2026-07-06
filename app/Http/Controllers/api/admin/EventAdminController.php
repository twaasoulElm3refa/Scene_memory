<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventAdminController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventRepositoryInterface $eventRepository)
    {
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            Log::info('Update Event Request', [
                'route_key' => $id,
                'data' => $request->except(['image']),
                'files' => array_keys($request->allFiles()),
            ]);

            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'start_date' => ['required'],
                'end_date' => ['required', 'after_or_equal:start_date'],
                'time' => ['nullable', 'string'],
                'image' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:10240'],
                'city_id' => ['nullable', 'exists:cities,id'],
                'sub_categorey_id' => ['nullable', 'exists:sub_categoreys,id'],
                'is_trending' => ['nullable', 'boolean'],
            ]);

            $event = $this->findEventByRouteKey($id);
            $oldSlug = $event->slug;

            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(5) . '-' . time();

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('events', 'public');
            }

            if ($request->has('is_trending')) {
                $data['is_trending'] = $request->boolean('is_trending');
            }

            $event->update($data);

            $event->translations()->updateOrCreate(
                ['locale' => app()->getLocale()],
                [
                    'title' => $event->title,
                    'description' => $event->description,
                ]
            );

            $this->clearEventsCache($oldSlug);
            $this->clearEventsCache($event->slug);

            $event = $event->fresh()
                ->load('city.translation', 'sub_categorey.translation', 'user:id,name', 'images', 'translation')
                ->loadCount('comments', 'likes', 'views');

            Log::info('Update Event Success', [
                'route_key' => $id,
                'event_id' => $event->id,
                'slug' => $event->slug,
                'is_trending' => $event->is_trending,
            ]);

            return $this->success($event, 'تم حفظ الحدث بنجاح');
        } catch (ValidationException $th) {
            Log::warning('Update Event Validation Failed', [
                'route_key' => $id,
                'errors' => $th->errors(),
            ]);

            return $this->validationError($th->errors(), 'من فضلك راجع الحقول المطلوبة');
        } catch (\Throwable $th) {
            Log::error('Update Event Failed', [
                'route_key' => $id,
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            return $this->error(
                config('app.debug') ? $th->getMessage() : 'حدث خطأ أثناء حفظ الحدث'
            );
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $event = $this->findEventByRouteKey($id);
        $slug = $event->slug;

        $event->delete();
        $this->clearEventsCache($slug);

        return $this->success($event, 'Event Deleted Successfully');
    }

    private function findEventByRouteKey(string $eventKey): Events
    {
        if (is_numeric($eventKey)) {
            $event = $this->eventRepository->findById((int) $eventKey);

            if ($event) {
                return $event;
            }
        }

        return $this->eventRepository->findBySlugOrFail($eventKey);
    }

    private function clearEventsCache(?string $slug = null): void
    {
        $perPage = 8;
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr'];

        for ($page = 1; $page <= 10; $page++) {
            $this->forgetEventsCache("events_page_{$page}_per_{$perPage}");

            foreach ($locales as $locale) {
                $this->forgetEventsCache("events_page_{$page}_per_{$perPage}_{$locale}");
                $this->forgetEventsCache("events_historical_page_{$page}_per_{$perPage}_{$locale}");
            }
        }

        if ($slug) {
            foreach ($locales as $locale) {
                $this->forgetEventsCache("events_single_{$slug}_{$locale}");
                $this->forgetEventsCache('event_' . strtolower(trim($slug)) . "_{$locale}");
            }
        }

        $this->forgetEventsCache('events_count');
        $this->forgetEventsCache('memories');
        $this->forgetEventsCache('daily_events_' . app()->getLocale());
    }

    private function forgetEventsCache(string $key): void
    {
        try {
            Cache::tags(['events'])->forget($key);
        } catch (\Throwable) {
            Cache::forget($key);
        }
    }
}
