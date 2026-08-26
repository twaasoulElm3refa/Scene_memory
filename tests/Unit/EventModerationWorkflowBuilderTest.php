<?php

namespace Tests\Unit;

use App\Services\N8n\EventModerationWorkflowBuilder;
use Tests\TestCase;

class EventModerationWorkflowBuilderTest extends TestCase
{
    public function test_it_builds_authenticated_strict_workflow_without_embedding_secrets(): void
    {
        config()->set('event_moderation.n8n.workflow_name', 'Scemory - Event Moderation');
        config()->set('event_moderation.n8n.webhook_path', 'scemory-event-moderation');
        config()->set('event_moderation.openrouter.api_url', 'https://openrouter.ai/api/v1');
        config()->set('event_moderation.openrouter.model', 'test/model');
        config()->set('event_moderation.openrouter.translation_model', 'test/translation-model');
        config()->set('event_moderation.laravel.base_url', 'https://laravel.test');
        config()->set('event_moderation.n8n.webhook_secret', 'must-not-be-embedded');
        config()->set('event_moderation.openrouter.api_key', 'also-must-not-be-embedded');

        $workflow = app(EventModerationWorkflowBuilder::class)->build(
            ['id' => 'webhook-credential', 'name' => 'Webhook Auth'],
            ['id' => 'openrouter-credential', 'name' => 'OpenRouter Auth'],
        );
        $encoded = json_encode($workflow, JSON_THROW_ON_ERROR);
        $nodes = collect($workflow['nodes'])->keyBy('name');

        $this->assertSame('Scemory - Event Moderation', $workflow['name']);
        $this->assertCount(13, $workflow['nodes']);
        $this->assertStringContainsString('json_schema', $encoded);
        $this->assertStringContainsString('reasoning', $encoded);
        $this->assertStringContainsString('manual_review', $encoded);
        $this->assertStringContainsString('OpenRouter Translation', $encoded);
        $this->assertStringContainsString('Store Translations in Laravel', $encoded);
        $this->assertStringContainsString(
            'test/translation-model',
            $nodes['Build Translation Request']['parameters']['jsCode']
        );
        $this->assertStringContainsString(
            'https://laravel.test/api/v1/moderation/events/',
            $nodes['Store Translations in Laravel']['parameters']['url']
        );
        $this->assertStringContainsString(
            '["ar","en","fr","es","zh","de","ru","it","ja","fa","ur","hi","tr"]',
            $nodes['Build Translation Request']['parameters']['jsCode']
        );
        $this->assertStringContainsString('webhook-credential', $encoded);
        $this->assertStringContainsString('openrouter-credential', $encoded);
        $this->assertStringNotContainsString('must-not-be-embedded', $encoded);
        $this->assertStringNotContainsString('also-must-not-be-embedded', $encoded);

        $approvedOutputs = $workflow['connections']['Is Approved for Publication']['main'];
        $this->assertSame('Return Approval to Laravel', $approvedOutputs[0][0]['node']);
        $this->assertSame('Return Decision to Laravel', $approvedOutputs[1][0]['node']);
        $this->assertSame(
            'Build Translation Request',
            $workflow['connections']['Return Approval to Laravel']['main'][0][0]['node']
        );
    }
}
