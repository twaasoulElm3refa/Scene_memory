<?php

namespace App\Jobs;

use App\Models\comments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $commentId;
    protected $text;

    public function __construct($commentId, $text)
    {
        $this->commentId = $commentId;
        $this->text = $text;
    }

    public function handle(): void
    {

        $comment = comments::find($this->commentId);
        if (!$comment) return;

        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi','tr'];

        foreach ($locales as $locale) {
            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('auto');
                $translated = $tr->translate($this->text);

                if ($translated) {
                    $comment->translations()->updateOrCreate(
                        ['locale' => $locale],
                        ['comment' => $translated]
                    );
                }

            } catch (\Exception $e) {
                \Log::error('Translate Comment Error: ' . $e->getMessage());
            }
        }
    }
}
