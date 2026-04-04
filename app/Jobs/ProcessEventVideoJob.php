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
        $event = Events::find($this->eventId);
        $fileContents = Storage::disk('public')->get($this->filePath);
        $finalPath = 'videos/' . basename($this->filePath);
        Storage::disk('public')->put($finalPath, $fileContents);
        eventsImges::create([
            'event_id' => $this->eventId,
            'full_url' => $finalPath,
            'price'=>15,
            'is_active' => 1,
        ]);
        $this->clearEventsCache($this->eventId);
        $this->clearEventCache($this->eventId);
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

    public function clearEventCache(string $slug): void
    {
        Cache::tags(['events'])->forget(
            'event_' . strtolower(trim($slug)) . '_' . app()->getLocale()
        );
    }
}
