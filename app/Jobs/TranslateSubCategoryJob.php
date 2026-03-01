<?php

namespace App\Jobs;

use App\Models\subCategorey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateSubCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $subCategoryId;
    protected $text;

    public function __construct($subCategoryId, $text)
    {
        $this->subCategoryId = $subCategoryId;
        $this->text = $text;
    }

    public function handle(): void
    {
        $subCategory = subCategorey::find($this->subCategoryId);

        if (! $subCategory) {
            return;
        }

        $locales = ['en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];

        foreach ($locales as $locale) {

            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('ar');

                $translated = $tr->translate($this->text);

                if ($translated) {
                    $subCategory->translations()->updateOrCreate(
                        ['locale' => $locale],
                        ['name'   => $translated]
                    );
                }

            } catch (\Exception $e) {
                \Log::error('SubCategory Translate Error: '.$e->getMessage());
            }
        }
    }
}