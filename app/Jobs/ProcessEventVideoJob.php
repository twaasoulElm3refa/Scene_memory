<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProcessEventVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $eventId;
    public $filePath;

    public function __construct($eventId, $filePath)
    {
        $this->eventId = $eventId;
        $this->filePath = $filePath;
    }

    public function handle()
    {
        $fileContents = Storage::disk('public')->get($this->filePath);
        $finalPath = 'videos/' . basename($this->filePath);
        Storage::disk('public')->put($finalPath, $fileContents);
        eventsImges::create([
            'event_id' => $this->eventId,
            'url' => $finalPath,
            'is_active' => 1,
        ]);
        $this->clearEventsCache(Events::find($this->eventId)->slug);
        Storage::disk('public')->delete($this->filePath);
    }

    private function clearEventsCache($slug = null)
    {
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        Cache::forget("events_single_{$slug}");
        foreach ($locales as $locale) {
            Cache::forget("events_single_{$slug}_".$locale);
        }
        Cache::flush();
    }
}
