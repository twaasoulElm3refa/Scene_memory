<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use Stichoza\GoogleTranslate\GoogleTranslate;

class EventAdminCreateController extends Controller
{
    use ApiResponse;

    public function create(EventsRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['urls']);

        try {

            $event = DB::transaction(function () use ($data, $request) {

                $data['slug'] = Str::slug($data['title']).'-'.Str::random(5).'-'.time();
                $data['user_id'] = auth()->id();

                $event = Events::create($data);

                /*
                |--------------------------------------------------------------------------
                | Save Arabic Translation (Base)
                |--------------------------------------------------------------------------
                */

                $event->translations()->create([
                    'locale' => 'ar',
                    'title' => $data['title'],
                    'description' => $data['description'],
                ]);

                /*
                |--------------------------------------------------------------------------
                | Auto Translate
                |--------------------------------------------------------------------------
                */

                $locales = ['en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];

                foreach ($locales as $locale) {

                    $translatedTitle = $this->translateFree($data['title'], 'ar', $locale);
                    $translatedDescription = $this->translateFree($data['description'], 'ar', $locale);

                    if ($translatedTitle && $translatedDescription) {
                        $event->translations()->create([
                            'locale' => $locale,
                            'title' => $translatedTitle,
                            'description' => $translatedDescription,
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Upload Images
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('urls')) {
                    foreach ($request->file('urls') as $file) {
                        $path = $file->store('Photos', 'public');

                        eventsImges::create([
                            'event_id' => $event->id,
                            'url' => $path,
                        ]);
                    }
                }

                return $event;
            });

            $this->clearEventsCache();

            return $this->success(
                $event->load('translations', 'photos'),
                'Event Created Successfully'
            );

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    private function translateFree($text, $source, $target)
    {
        try {

            $tr = new GoogleTranslate($target);
            $tr->setSource($source);

            return $tr->translate($text);

        } catch (\Exception $e) {

            \Log::error('Translate Error: '.$e->getMessage());

            return null;
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
