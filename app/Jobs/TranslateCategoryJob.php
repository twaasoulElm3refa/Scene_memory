<?php

namespace App\Jobs;

use App\Models\Categories;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $categoryId;
    protected $text;

    public function __construct($categoryId, $text , )
    {
        $this->categoryId = $categoryId;
        $this->text = $text;
    }

    public function handle(): void
    {
        $category = Categories::find($this->categoryId);

        if (! $category) {
            return;
        }

        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];

        foreach ($locales as $locale) {

            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('ar');
                $translated = $tr->translate($this->text);

                if ($translated) {
                    $category->translations()->updateOrCreate(
                        ['locale' => $locale],
                        ['name' => $translated]
                    );
                }

            } catch (\Exception $e) {
                \Log::error('Translate Error: '.$e->getMessage());
            }
        }
    }
}
