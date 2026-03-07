<?php

namespace App\Jobs;

use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateEventJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    protected $eventId;
    protected $title;
    protected $description;

    public function __construct($eventId, $title, $description)
    {
        $this->eventId = $eventId;
        $this->title = $title;
        $this->description = $description;
    }

    public function handle(): void
    {
        $event = Events::find($this->eventId);

        if (!$event) {
            return;
        }

        $locales = ['ar','en','fr','es','zh','de','ru','it','ja','fa','ur','hi'];

        try {

            $tr = new GoogleTranslate();
            $tr->setSource('auto');
            $tr->setTarget('en');
            $tr->translate($this->title);

            $sourceLang = $tr->getLastDetectedSource() ?: 'en';
            $event->translations()->updateOrCreate(
                ['locale' => $sourceLang],
                [
                    'title' => $this->title,
                    'description' => $this->description,
                ]
            );

            foreach ($locales as $locale) {

                if ($locale === $sourceLang) {
                    continue;
                }

                try {

                    $tr->setSource($sourceLang);
                    $tr->setTarget($locale);

                    $translatedTitle = $tr->translate($this->title);
                    $translatedDescription = $tr->translate($this->description);

                    $event->translations()->updateOrCreate(
                        ['locale' => $locale],
                        [
                            'title' => $translatedTitle,
                            'description' => $translatedDescription,
                        ]
                    );

                } catch (\Exception $e) {
                    \Log::error("Translation error to {$locale}: ".$e->getMessage());
                }
            }

        } catch (\Exception $e) {
            \Log::error('Detect Language Error: '.$e->getMessage());
        }
    }
}
