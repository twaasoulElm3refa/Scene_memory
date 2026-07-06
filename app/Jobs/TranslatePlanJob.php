<?php

namespace App\Jobs;

use App\Models\LicenceType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslatePlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $planId;
    protected $text;

    public function __construct($planId, $text)
    {
        $this->planId = $planId;
        $this->text = $text;
    }

    public function handle(): void
    {
        $plan = licenceType::find($this->planId);
        if (! $plan) return;
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi','tr'];

        foreach ($locales as $locale) {
            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('auto');

                $translated = $tr->translate($this->text);

                if ($translated) {
                    $plan->translations()->updateOrCreate(
                        ['locale' => $locale],
                        ['name' => $translated]
                    );
                }

            } catch (\Exception $e) {
                \Log::error('Translate Plan Error: ' . $e->getMessage());
            }
        }
    }
}
