<?php

namespace App\Jobs;

use App\Models\BenefitsTranslations;
use App\Models\PlanBenefits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateBenefitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $benefitId;
    protected $text;

    public function __construct($benefitId, $text)
    {
        $this->benefitId = $benefitId;
        $this->text = $text;
    }

    public function handle(): void
    {
        $benefit = PlanBenefits::find($this->benefitId);

        if (! $benefit) {
            \Log::error('Benefit not found: ' . $this->benefitId);
            return;
        }

        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr'];

        foreach ($locales as $locale) {
            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('auto');

                $translated = $tr->translate($this->text);

                if ($translated) {
                    BenefitsTranslations::updateOrCreate(
                        [
                            'benefit_id' => $benefit->id,
                            'locale' => $locale
                        ],
                        [
                            'name' => $translated
                        ]
                    );
                }

            } catch (\Exception $e) {
                \Log::error("Translate Error [{$locale}]: " . $e->getMessage());
            }
        }

        \Log::info('Benefit Translated: ' . $benefit->id);
    }
}
