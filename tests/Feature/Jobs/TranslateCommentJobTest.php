<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TranslateCommentJob;
use App\Models\Comments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslateCommentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_comment_has_only_english_and_arabic_translations(): void
    {
        $comment = Comments::create(['comment' => 'Hello']);
        $comment->translations()->create(['locale' => 'fr', 'comment' => 'Bonjour']);

        $job = new class($comment->id, 'Hello') extends TranslateCommentJob
        {
            protected function translateTo(string $locale): array
            {
                return [
                    'text' => $locale === 'ar' ? 'مرحبا' : 'Translated English',
                    'source' => 'en',
                ];
            }
        };

        $job->handle();

        $this->assertSame(['ar', 'en'], $comment->translations()->orderBy('locale')->pluck('locale')->all());
        $this->assertSame('Hello', $comment->translations()->where('locale', 'en')->value('comment'));
        $this->assertSame('مرحبا', $comment->translations()->where('locale', 'ar')->value('comment'));
    }

    public function test_arabic_comment_preserves_arabic_original_and_creates_only_english_translation(): void
    {
        $comment = Comments::create(['comment' => 'أهلا']);

        $job = new class($comment->id, 'أهلا') extends TranslateCommentJob
        {
            protected function translateTo(string $locale): array
            {
                return [
                    'text' => $locale === 'en' ? 'Hello' : 'Translated Arabic',
                    'source' => 'ar',
                ];
            }
        };

        $job->handle();

        $this->assertSame(['ar', 'en'], $comment->translations()->orderBy('locale')->pluck('locale')->all());
        $this->assertSame('أهلا', $comment->translations()->where('locale', 'ar')->value('comment'));
        $this->assertSame('Hello', $comment->translations()->where('locale', 'en')->value('comment'));
    }
}
