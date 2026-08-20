<?php

namespace Tests\Feature\Console;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetupScemoryN8nCommandTest extends TestCase
{
    public function test_setup_is_idempotent_and_updates_the_same_workflow(): void
    {
        config()->set('event_moderation.n8n.base_url', 'https://n8n.test');
        config()->set('event_moderation.n8n.api_key', 'n8n-api-key');
        config()->set('event_moderation.n8n.webhook_secret', str_repeat('s', 32));
        config()->set('event_moderation.n8n.workflow_name', 'Scemory - Event Moderation');
        config()->set('event_moderation.n8n.webhook_path', 'scemory-event-moderation');
        config()->set('event_moderation.openrouter.api_key', 'openrouter-api-key');
        config()->set('event_moderation.openrouter.api_url', 'https://openrouter.ai/api/v1');
        config()->set('event_moderation.openrouter.model', 'test/model');
        config()->set('event_moderation.admin_email', 'admin@example.com');

        $state = [
            'workflow' => null,
            'credentials' => [],
            'workflow_creates' => 0,
            'workflow_updates' => 0,
        ];

        Http::fake(function (Request $request) use (&$state) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $method = $request->method();

            if ($method === 'GET' && $path === '/api/v1/credentials/schema/httpHeaderAuth') {
                return Http::response(['properties' => ['name' => [], 'value' => []]]);
            }

            if ($method === 'GET' && $path === '/api/v1/credentials') {
                return Http::response([
                    'data' => array_values($state['credentials']),
                    'nextCursor' => null,
                ]);
            }

            if ($method === 'POST' && $path === '/api/v1/credentials') {
                $id = 'credential-'.(count($state['credentials']) + 1);
                $credential = [
                    'id' => $id,
                    'name' => $request->data()['name'],
                    'type' => $request->data()['type'],
                ];
                $state['credentials'][$id] = $credential;

                return Http::response($credential);
            }

            if ($method === 'PATCH' && str_starts_with($path, '/api/v1/credentials/')) {
                $id = basename($path);
                $state['credentials'][$id] = [
                    'id' => $id,
                    'name' => $request->data()['name'],
                    'type' => $request->data()['type'],
                ];

                return Http::response($state['credentials'][$id]);
            }

            if ($method === 'GET' && $path === '/api/v1/workflows') {
                return Http::response([
                    'data' => $state['workflow'] === null
                        ? []
                        : [[
                            'id' => $state['workflow']['id'],
                            'name' => $state['workflow']['name'],
                            'active' => $state['workflow']['active'],
                            'createdAt' => '2026-08-20T00:00:00Z',
                        ]],
                    'nextCursor' => null,
                ]);
            }

            if ($method === 'POST' && $path === '/api/v1/workflows') {
                $state['workflow_creates']++;
                $state['workflow'] = array_merge($request->data(), [
                    'id' => 'workflow-1',
                    'active' => false,
                ]);

                return Http::response($state['workflow']);
            }

            if ($method === 'PUT' && $path === '/api/v1/workflows/workflow-1') {
                $state['workflow_updates']++;
                $state['workflow'] = array_merge($request->data(), [
                    'id' => 'workflow-1',
                    'active' => true,
                ]);

                return Http::response($state['workflow']);
            }

            if ($method === 'POST' && $path === '/api/v1/workflows/workflow-1/activate') {
                $state['workflow']['active'] = true;

                return Http::response($state['workflow']);
            }

            if ($method === 'GET' && $path === '/api/v1/workflows/workflow-1') {
                return Http::response($state['workflow']);
            }

            return Http::response(['message' => "Unexpected {$method} {$path}"], 500);
        });

        $this->artisan('scemory:setup-n8n')->assertSuccessful();
        $this->artisan('scemory:setup-n8n')->assertSuccessful();

        $this->assertSame(1, $state['workflow_creates']);
        $this->assertSame(1, $state['workflow_updates']);
        $this->assertTrue($state['workflow']['active']);
        $this->assertCount(2, $state['credentials']);
        $this->assertCount(5, $state['workflow']['nodes']);
    }
}
