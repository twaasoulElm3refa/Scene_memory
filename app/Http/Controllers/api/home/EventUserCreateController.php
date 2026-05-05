<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Jobs\ProcessEventImageJob;
use App\Jobs\ProcessEventVideoJob;
use App\Jobs\TranslateEventJob;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventUserCreateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly RequestRepositoryInterface $requestRepository
    ) {
    }

    public function create(EventsRequest $request)
    {
        $data = $request->validated();
        unset($data['urls']);

        try {
            $event = DB::transaction(function () use ($data, $request) {
                // لاحظ: مش محتاجين $imageAnalysisService جوه الـ transaction دلوقتي
                $data['slug']    = Str::slug($data['title']) . '-' . Str::random(5) . '-' . time();
                $data['user_id'] = auth()->id();
                $data['is_active'] = 0;

                $event = $this->eventRepository->create($data);

                $this->requestRepository->createEventRequest(['event_id' => $event->id]);

                $event->translations()->create([
                    'locale'      => 'ar',
                    'title'       => $data['title'],
                    'description' => $data['description'],
                ]);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls', []) as $file) {
                        if (!$file instanceof \Illuminate\Http\UploadedFile) {
                            \Log::error('Invalid uploaded item in urls', ['type' => gettype($file)]);
                            continue;
                        }

                        $mime = (string) $file->getMimeType();

                        $supportedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];

                        if (in_array($mime, $supportedImageMimes, true)) {
                            // ✅ بس نخزن temp ونـ dispatch — مفيش processing هنا
                            $tempPath = $file->store('images_temp', 'public');
                            ProcessEventImageJob::dispatch($event->id, $tempPath);

                        } elseif (str_starts_with($mime, 'video/')) {
                            try {
                                $path = $file->store('videos_temp', 'public');
                                ProcessEventVideoJob::dispatch($event->id, $path);
                            } catch (\Throwable $e) {
                                \Log::error('Video processing dispatch failed', [
                                    'name'    => $file->getClientOriginalName(),
                                    'mime'    => $mime,
                                    'message' => $e->getMessage(),
                                    'file'    => $e->getFile(),
                                    'line'    => $e->getLine(),
                                ]);
                                throw $e;
                            }
                        } else {
                            \Log::warning('Unsupported upload type skipped', [
                                'name' => $file->getClientOriginalName(),
                                'mime' => $mime,
                            ]);
                        }
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch($event->id, $data['title'], $data['description']);

            $this->clearEventsCache($event->slug);

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );

        } catch (\Throwable $th) {
            \Log::error('Event create failed', [
                'message' => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
                'trace'   => $th->getTraceAsString(),
            ]);

            return $this->error($th->getMessage());
        }
    }

    public function historic(EventsRequest $request)
    {
        $data = $request->validated();
        unset($data['urls']);

        try {
            $event = DB::transaction(function () use ($data, $request) {
                // لاحظ: مش محتاجين $imageAnalysisService جوه الـ transaction دلوقتي
                $data['slug']    = Str::slug($data['title']) . '-' . Str::random(5) . '-' . time();
                $data['user_id'] = auth()->id();
                $data['is_active'] = 0;
                $data['is_historical'] = 1;

                $event = $this->eventRepository->create($data);

                $this->requestRepository->createEventRequest(['event_id' => $event->id]);

                $event->translations()->create([
                    'locale'      => 'ar',
                    'title'       => $data['title'],
                    'description' => $data['description'],
                ]);

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls', []) as $file) {
                        if (!$file instanceof \Illuminate\Http\UploadedFile) {
                            \Log::error('Invalid uploaded item in urls', ['type' => gettype($file)]);
                            continue;
                        }

                        $mime = (string) $file->getMimeType();

                        $supportedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];

                        if (in_array($mime, $supportedImageMimes, true)) {
                            // ✅ بس نخزن temp ونـ dispatch — مفيش processing هنا
                            $tempPath = $file->store('images_temp', 'public');
                            ProcessEventImageJob::dispatch($event->id, $tempPath);

                        } elseif (str_starts_with($mime, 'video/')) {
                            try {
                                $path = $file->store('videos_temp', 'public');
                                ProcessEventVideoJob::dispatch($event->id, $path);
                            } catch (\Throwable $e) {
                                \Log::error('Video processing dispatch failed', [
                                    'name'    => $file->getClientOriginalName(),
                                    'mime'    => $mime,
                                    'message' => $e->getMessage(),
                                    'file'    => $e->getFile(),
                                    'line'    => $e->getLine(),
                                ]);
                                throw $e;
                            }
                        } else {
                            \Log::warning('Unsupported upload type skipped', [
                                'name' => $file->getClientOriginalName(),
                                'mime' => $mime,
                            ]);
                        }
                    }
                }

                return $event;
            });

            TranslateEventJob::dispatch($event->id, $data['title'], $data['description']);

            $this->clearEventsCache($event->slug);

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );

        } catch (\Throwable $th) {
            \Log::error('Event create failed', [
                'message' => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
                'trace'   => $th->getTraceAsString(),
            ]);

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
        Cache::tags(['requests'])->flush();
    }
}
