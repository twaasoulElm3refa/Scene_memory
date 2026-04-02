<?php

namespace App\Jobs;

use App\Models\Cities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateCityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cityId;
    protected $text;

    public function __construct($cityId, $text)
    {
        $this->cityId = $cityId;
        $this->text = $text;
    }

    public function handle(): void
    {

        $city = Cities::find($this->cityId);

        if (! $city) {
            return;
        }

        $locales = ['ar','en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi','tr'];

        foreach ($locales as $locale) {

            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('ar');

                $translated = $tr->translate($this->text);

                if ($translated) {
                    $city->translations()->updateOrCreate(
                        ['locale' => $locale],
                        ['name' => $translated]
                    );
                }

            } catch (\Exception $e) {
                \Log::error('City Translate Error: '.$e->getMessage());
            }
        }
    }
}
