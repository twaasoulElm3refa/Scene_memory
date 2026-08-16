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

    public const TARGET_LOCALES = ['ar', 'en'];

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
        if (! $comment) {
            return;
        }

        $comment->translations()
            ->whereNotIn('locale', self::TARGET_LOCALES)
            ->delete();

        foreach (self::TARGET_LOCALES as $locale) {
            try {
                $translation = $this->translateTo($locale);

                if ($translation['text']) {
                    $comment->translations()->updateOrCreate(
                        ['locale' => $locale],
                        [
                            'comment' => $translation['source'] === $locale
                                ? $this->text
                                : $translation['text'],
                        ]
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Translate Comment Error: '.$e->getMessage());
            }
        }
    }

    protected function translateTo(string $locale): array
    {
        $translator = new GoogleTranslate($locale);
        $translator->setSource('auto');

        return [
            'text' => $translator->translate($this->text),
            'source' => $translator->getLastDetectedSource(),
        ];
    }
}
