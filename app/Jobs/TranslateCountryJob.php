<?php

namespace App\Jobs;

use App\Models\Countries;
use App\Services\LocationCacheService;
use App\Support\EventTranslationLocales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateCountryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $countryId;

    protected $text;

    public function __construct($countryId, $text)
    {
        $this->countryId = $countryId;
        $this->text = $text;
    }

    public function handle(LocationCacheService $cache): void
    {
        $country = Countries::find($this->countryId);

        if (! $country) {
            return;
        }

        foreach (EventTranslationLocales::ALL as $locale) {
            try {
                if ($locale === 'ar') {
                    $translated = $this->text;
                } else {
                    $tr = new GoogleTranslate($locale);
                    $tr->setSource('ar');
                    $translated = $tr->translate($this->text);
                }

                if ($translated) {
                    $country->translations()->updateOrCreate(
                        ['locale' => $locale],
                        ['name' => $translated]
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Country Translate Error: '.$e->getMessage());
            }
        }

        $cache->invalidate();
    }
}
