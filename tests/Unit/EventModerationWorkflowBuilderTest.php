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
        config()->set('event_moderation.openrouter.model', 'test/moderation-model');
        config()->set('event_moderation.openrouter.translation_model', 'openrouter/free');
        config()->set('event_moderation.auto_decision_threshold', 0.70);
        config()->set('event_moderation.laravel.base_url', 'https://laravel.test');
        config()->set('event_moderation.n8n.webhook_secret', 'must-not-be-embedded');
        config()->set('event_moderation.openrouter.api_key', 'also-must-not-be-embedded');

        $workflow = app(EventModerationWorkflowBuilder::class)->build(
            ['id' => 'webhook-credential', 'name' => 'Webhook Auth'],
            ['id' => 'openrouter-credential', 'name' => 'OpenRouter Auth'],
            ['id' => 'openrouter-translation-credential', 'name' => 'OpenRouter Translation Auth'],
        );
        $encoded = json_encode($workflow, JSON_THROW_ON_ERROR);
        $nodes = collect($workflow['nodes'])->keyBy('name');

        $this->assertSame('Scemory - Event Moderation', $workflow['name']);
        $this->assertCount(13, $workflow['nodes']);
        $this->assertStringContainsString('json_schema', $encoded);
        $this->assertStringContainsString('reasoning', $encoded);
        $this->assertStringContainsString('manual_review', $encoded);
        $this->assertStringContainsString('OpenRouter Translation', $encoded);
        $this->assertStringNotContainsString('Google Cloud Translation', $encoded);
        $this->assertStringContainsString('Store Translations in Laravel', $encoded);
        $this->assertStringContainsString(
            'openrouter/free',
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
        $this->assertStringContainsString('openrouter-translation-credential', $encoded);
        $this->assertStringNotContainsString('must-not-be-embedded', $encoded);
        $this->assertStringNotContainsString('also-must-not-be-embedded', $encoded);
        $this->assertStringContainsString(
            '>= 0.7',
            $nodes['Is Approved for Publication']['parameters']['conditions']['conditions'][0]['leftValue']
        );

        $approvedOutputs = $workflow['connections']['Is Approved for Publication']['main'];
        $this->assertSame('Return Approval to Laravel', $approvedOutputs[0][0]['node']);
        $this->assertSame('Return Decision to Laravel', $approvedOutputs[1][0]['node']);
        // Approval returns to Laravel immediately and does not connect to translation directly:
        $this->assertArrayNotHasKey('Return Approval to Laravel', $workflow['connections']);
        // Translation only triggers after accepted translation request:
        $this->assertSame(
            'Build Translation Request',
            $workflow['connections']['Return Translation Request Accepted']['main'][0][0]['node']
        );
        $this->assertSame(
            'OpenRouter Translation',
            $workflow['connections']['Build Translation Request']['main'][0][0]['node']
        );
        $this->assertSame(
            'Parse and Validate Translations',
            $workflow['connections']['OpenRouter Translation']['main'][0][0]['node']
        );
    }
}
