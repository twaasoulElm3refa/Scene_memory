<?php

namespace App\Services\ImageTags;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class PromptBuilder
{
    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    public function buildTagsPrompt(
        string $title,
        ?string $description,
        int $imagesCount,
        string $language
    ): string {
        $eventTagsLimit = $this->eventTagsLimit();
        $imageTagsLimit = $this->imageTagsLimit();

        return <<<PROMPT
Analyze the provided news content and image(s).

Language for tags: {$language}

Title:
{$title}

Description:
{$description}

Instructions:
1. Extract "event_tags" from the title and description only.
2. Extract "image_tags" from each image based only on visible content in that image.
3. Do not identify people by name unless absolutely certain.
4. Do not include hashtags.
5. Do not include explanations.
6. Return valid JSON only.
7. Keep event_tags concise and relevant.
8. Return no more than {$eventTagsLimit} event_tags.
9. Return no more than {$imageTagsLimit} tags for each image.
10. The number of image objects must be exactly {$imagesCount}.
11. Keep every tag at 50 characters or fewer.

Return exactly in this JSON structure:
{
  "event_tags": ["tag 1", "tag 2"],
  "images": [
    {
      "image_index": 1,
      "tags": ["tag 1", "tag 2"]
    }
  ]
}
PROMPT;
    }

    public function buildModerationPrompt(string $title, ?string $description): string
    {
        return <<<PROMPT
You are a strict content moderation classifier for event submissions.

Check the title and description for:
- profanity and obscene language
- insults, bullying, and degrading language
- sexually explicit content or sexual innuendo
- hate speech or discrimination
- threats, harassment, incitement, or calls for violence
- offensive words hidden with spaces, symbols, numbers, repeated characters, or separated letters

Cover Arabic and English, including Egyptian Arabic and common Arabic dialects.

Return valid JSON only, exactly one of:
{"flagged":true}
{"flagged":false}

Title:
{$title}

Description:
{$description}
PROMPT;
    }

    private function eventTagsLimit(): int
    {
        return max(0, (int) $this->config->get('ai_tags.event_tags_limit', 8));
    }

    private function imageTagsLimit(): int
    {
        return max(0, (int) $this->config->get('ai_tags.image_tags_limit', 10));
    }
}
