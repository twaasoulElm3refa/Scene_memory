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
        config()->set('event_moderation.n8n.webhook_secret', 'must-not-be-embedded');
        config()->set('event_moderation.openrouter.api_key', 'also-must-not-be-embedded');

        $workflow = app(EventModerationWorkflowBuilder::class)->build(
            ['id' => 'webhook-credential', 'name' => 'Webhook Auth'],
            ['id' => 'openrouter-credential', 'name' => 'OpenRouter Auth'],
        );
        $encoded = json_encode($workflow, JSON_THROW_ON_ERROR);

        $this->assertSame('Scemory - Event Moderation', $workflow['name']);
        $this->assertCount(5, $workflow['nodes']);
        $this->assertStringContainsString('json_schema', $encoded);
        $this->assertStringContainsString('reasoning', $encoded);
        $this->assertStringContainsString('manual_review', $encoded);
        $this->assertStringContainsString('webhook-credential', $encoded);
        $this->assertStringContainsString('openrouter-credential', $encoded);
        $this->assertStringNotContainsString('must-not-be-embedded', $encoded);
        $this->assertStringNotContainsString('also-must-not-be-embedded', $encoded);
    }
}
