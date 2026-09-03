<?php

namespace App\Jobs;

use App\Models\Cities;
use App\Services\LocationCacheService;
use App\Support\EventTranslationLocales;
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

    protected $sourceLocale;

    public function __construct($cityId, $text, $sourceLocale = 'ar')
    {
        $this->cityId = $cityId;
        $this->text = $text;
        $this->sourceLocale = $sourceLocale;
    }

    public function handle(LocationCacheService $cache): void
    {

        $city = Cities::find($this->cityId);

        if (! $city) {
            return;
        }

        foreach (EventTranslationLocales::ALL as $locale) {

            try {
                if ($locale === $this->sourceLocale) {
                    $translated = $this->text;
                } else {
                    $tr = new GoogleTranslate($locale);
                    $tr->setSource($this->sourceLocale);
                    $translated = $tr->translate($this->text);
                }

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

        $cache->invalidate();
    }
}
