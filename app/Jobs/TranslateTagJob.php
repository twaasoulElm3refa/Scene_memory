<?php

namespace App\Jobs;

use App\Models\Tags;
use App\Models\TagsTranslations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;
class TranslateTagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tagId;
    protected $text;
    /**
     * Create a new job instance.
     */
    public function __construct($tagId, $text)
    {
        $this->tagId = $tagId;
        $this->text = $text;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tag = Tags::find($this->tagId);

        if (! $tag) {
            Log::error('tag not found: ' . $this->tagId);
            return;
        }

        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi', 'tr'];

        foreach ($locales as $locale) {
            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('auto');

                $translated = $tr->translate($this->text);

                if ($translated) {
                    TagsTranslations::updateOrCreate(
                        [
                            'tag_id' => $tag->id,
                            'locale' => $locale
                        ],
                        [
                            'name' => $translated
                        ]
                    );
                }

            } catch (\Exception $e) {
                Log::error("Translate Error [{$locale}]: " . $e->getMessage());
            }
        }

        Log::info('Benefit Translated: ' . $tag->id);
    }
}
