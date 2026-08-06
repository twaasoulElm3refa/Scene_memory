<?php

namespace App\Jobs;

use App\Models\EventsImges;
use App\Models\ImageTranslations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateImageJob implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $imageId;
    protected $text;
    /**
     * Create a new job instance.
     */
    public function __construct($imageId, $text)
    {
        $this->imageId = $imageId;
        $this->text = $text;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $image = EventsImges::find($this->imageId);

        if (! $image) {
            Log::error('Image not found: ' . $this->imageId);
            return;
        }

        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr'];

        foreach ($locales as $locale) {
            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('auto');

                $translated = $tr->translate($this->text);

                if ($translated) {
                    ImageTranslations::updateOrCreate(
                        [
                            'image_id' => $image->id,
                            'locale' => $locale
                        ],
                        [
                            'description' => $translated
                        ]
                    );
                }

            } catch (\Exception $e) {
                Log::error("Translate Error [{$locale}]: " . $e->getMessage());
            }
        }

        Log::info('Image Translated: ' . $image->id);
    }
}
