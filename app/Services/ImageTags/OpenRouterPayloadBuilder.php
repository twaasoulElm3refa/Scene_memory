<?php

namespace App\Services\ImageTags;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class OpenRouterPayloadBuilder
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly PromptBuilder $promptBuilder
    ) {}

    /**
     * @param  array{title: string, description?: ?string, language?: string, image_data_urls?: array<int, string>}  $validated
     */
    public function buildTagsPayload(array $validated): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => $this->promptBuilder->buildTagsPrompt(
                    title: $validated['title'],
                    description: $validated['description'] ?? null,
                    imagesCount: count($validated['image_data_urls'] ?? []),
                    language: $validated['language'] ?? 'ar',
                ),
            ],
        ];

        foreach ($validated['image_data_urls'] ?? [] as $dataUrl) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $dataUrl,
                ],
            ];
        }

        return [
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 300,
        ];
    }

    public function buildModerationPayload(
        string $title,
        ?string $description,
        int $maxCompletionTokens = 256
    ): array {
        return [
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $this->promptBuilder->buildModerationPrompt($title, $description),
                        ],
                    ],
                ],
            ],
            'temperature' => 0,
            'max_completion_tokens' => $maxCompletionTokens,
            'reasoning' => [
                'enabled' => false,
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
        ];
    }

    private function model(): string
    {
        return (string) $this->config->get('services.openrouter.model', 'openrouter/free');
    }
}
