<?php

namespace App\Jobs;

use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateEventJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    protected $eventId;
    protected $title;
    protected $description;
    protected $lang;

    public function __construct($eventId, $title, $description ,$lang="en")
    {
        $this->eventId = $eventId;
        $this->title = $title;
        $this->description = $description;
        $this->lang = $lang;
    }

    public function handle(): void
    {
        $event = Events::find($this->eventId);

        if (! $event) {
            return;
        }

        $locales = ['ar','en','fr','es','zh','de','ru','it','ja','fa','ur','hi'];

        foreach ($locales as $locale) {

            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('ar');

                $translatedTitle = $tr->translate($this->title);
                $translatedDescription = $tr->translate($this->description);

                if ($translatedTitle && $translatedDescription) {
                    $event->translations()->updateOrCreate(
                        ['locale' => $locale],
                        [
                            'title' => $translatedTitle,
                            'description' => $translatedDescription,
                        ]
                    );
                }

            } catch (\Exception $e) {
                \Log::error('Event Translate Error: '.$e->getMessage());
            }
        }
    }
}
