<?php

namespace Tests\Unit;

use App\Services\GenerateImageTagsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateImageTagsServiceTest extends TestCase
{
    public function test_uploaded_images_use_the_same_single_request_and_normalized_response_contract(): void
    {
        config()->set('services.openrouter.api_key', 'test-key');
        config()->set('services.openrouter.api_url', 'https://openrouter.test/api/v1');
        config()->set('services.openrouter.model', 'test/vision-model');
        config()->set('ai_tags.event_tags_limit', 2);
        config()->set('ai_tags.image_tags_limit', 2);

        Http::fake(function ($request) {
            $this->assertSame(['enabled' => false], $request->data()['reasoning']);
            $this->assertSame(['type' => 'json_object'], $request->data()['response_format']);

            $content = $request->data()['messages'][0]['content'];
            $this->assertCount(3, $content);
            $this->assertSame('text', $content[0]['type']);
            $this->assertStringStartsWith(
                'data:image/png;base64,',
                $content[1]['image_url']['url']
            );
            $this->assertStringStartsWith(
                'data:image/png;base64,',
                $content[2]['image_url']['url']
            );

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => <<<'JSON'
                            {
                                "event_tags": [" One ", "one", "Two", "Three"],
                                "images": [
                                    {"image_index": 2, "tags": ["B", "b", "C"]},
                                    {"image_index": 1, "tags": ["A", "D", "E"]}
                                ]
                            }
                            JSON,
                    ],
                ]],
            ]);
        });

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        $result = app(GenerateImageTagsService::class)->handle([
            'title' => 'Title',
            'description' => 'Description',
            'language' => 'ar',
            'images' => [
                UploadedFile::fake()->createWithContent('first.png', $png),
                UploadedFile::fake()->createWithContent('second.png', $png),
            ],
        ]);

        $this->assertSame(['One', 'Two'], $result['event_tags']);
        $this->assertSame(2, $result['images'][0]['image_index']);
        $this->assertSame(['B', 'C'], $result['images'][0]['tags']);
        $this->assertSame(1, $result['images'][1]['image_index']);
        $this->assertSame(['A', 'D'], $result['images'][1]['tags']);
        Http::assertSentCount(1);
    }

    public function test_it_extracts_json_when_provider_wraps_response_in_markdown_or_text(): void
    {
        config()->set('services.openrouter.api_key', 'test-key');
        config()->set('services.openrouter.api_url', 'https://openrouter.test/api/v1');
        config()->set('services.openrouter.model', 'test/vision-model');
        config()->set('ai_tags.event_tags_limit', 8);
        config()->set('ai_tags.image_tags_limit', 10);

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => <<<'TEXT'
                            Here is the JSON:
                            ```json
                            {"event_tags":["Wrapped"],"images":[{"image_index":1,"tags":["Photo"]}]}
                            ```
                            TEXT,
                    ],
                ]],
            ]),
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        $result = app(GenerateImageTagsService::class)->handle([
            'title' => 'Title',
            'description' => 'Description',
            'language' => 'ar',
            'images' => [
                UploadedFile::fake()->createWithContent('first.png', $png),
            ],
        ]);

        $this->assertSame(['Wrapped'], $result['event_tags']);
        $this->assertSame(1, $result['images'][0]['image_index']);
        $this->assertSame(['Photo'], $result['images'][0]['tags']);
    }
}
