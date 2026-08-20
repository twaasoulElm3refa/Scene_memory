<?php

namespace App\Services\N8n;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class EventModerationWorkflowBuilder
{
    public const WEBHOOK_CREDENTIAL_NAME = 'Scemory Event Moderation Webhook Auth';

    public const OPENROUTER_CREDENTIAL_NAME = 'Scemory OpenRouter Moderation';

    public const HEADER_CREDENTIAL_TYPE = 'httpHeaderAuth';

    public function __construct(private readonly ConfigRepository $config) {}

    /**
     * @param  array{id: string, name: string}  $webhookCredential
     * @param  array{id: string, name: string}  $openRouterCredential
     * @return array<string, mixed>
     */
    public function build(
        array $webhookCredential,
        array $openRouterCredential
    ): array {
        return [
            'name' => (string) $this->config->get(
                'event_moderation.n8n.workflow_name',
                'Scemory - Event Moderation'
            ),
            'nodes' => [
                [
                    'parameters' => [
                        'httpMethod' => 'POST',
                        'path' => trim((string) $this->config->get(
                            'event_moderation.n8n.webhook_path',
                            'scemory-event-moderation'
                        ), '/'),
                        'authentication' => 'headerAuth',
                        'responseMode' => 'responseNode',
                        'options' => [],
                    ],
                    'id' => '7cc84461-84ef-4e13-a669-b114440e14da',
                    'name' => 'Scemory Moderation Webhook',
                    'type' => 'n8n-nodes-base.webhook',
                    'typeVersion' => 2,
                    'position' => [-700, 0],
                    'webhookId' => 'scemory-event-moderation',
                    'credentials' => [
                        self::HEADER_CREDENTIAL_TYPE => $webhookCredential,
                    ],
                ],
                [
                    'parameters' => [
                        'jsCode' => $this->prepareRequestCode(),
                    ],
                    'id' => '43c39a77-1144-449d-8821-dd98616032cf',
                    'name' => 'Validate and Build AI Request',
                    'type' => 'n8n-nodes-base.code',
                    'typeVersion' => 2,
                    'position' => [-420, 0],
                ],
                [
                    'parameters' => [
                        'method' => 'POST',
                        'url' => $this->openRouterEndpoint(),
                        'authentication' => 'genericCredentialType',
                        'genericAuthType' => self::HEADER_CREDENTIAL_TYPE,
                        'sendBody' => true,
                        'specifyBody' => 'json',
                        'jsonBody' => '={{ $json }}',
                        'options' => [
                            'timeout' => 60000,
                        ],
                    ],
                    'id' => 'e08c76b8-68bf-46b7-a3ea-bff118268261',
                    'name' => 'OpenRouter Moderation',
                    'type' => 'n8n-nodes-base.httpRequest',
                    'typeVersion' => 4.2,
                    'position' => [-120, 0],
                    'credentials' => [
                        self::HEADER_CREDENTIAL_TYPE => $openRouterCredential,
                    ],
                ],
                [
                    'parameters' => [
                        'jsCode' => $this->parseResponseCode(),
                    ],
                    'id' => '71aa6fb8-d651-4a95-8107-52a1103c1684',
                    'name' => 'Parse and Validate Decision',
                    'type' => 'n8n-nodes-base.code',
                    'typeVersion' => 2,
                    'position' => [180, 0],
                ],
                [
                    'parameters' => [
                        'respondWith' => 'json',
                        'responseBody' => '={{ $json }}',
                        'options' => [
                            'responseCode' => 200,
                        ],
                    ],
                    'id' => 'd72a68d5-f068-4864-94a9-711b40d9665f',
                    'name' => 'Return Decision to Laravel',
                    'type' => 'n8n-nodes-base.respondToWebhook',
                    'typeVersion' => 1.4,
                    'position' => [480, 0],
                ],
            ],
            'connections' => [
                'Scemory Moderation Webhook' => [
                    'main' => [[[
                        'node' => 'Validate and Build AI Request',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'Validate and Build AI Request' => [
                    'main' => [[[
                        'node' => 'OpenRouter Moderation',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'OpenRouter Moderation' => [
                    'main' => [[[
                        'node' => 'Parse and Validate Decision',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'Parse and Validate Decision' => [
                    'main' => [[[
                        'node' => 'Return Decision to Laravel',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
            ],
            'settings' => [
                'executionOrder' => 'v1',
                'saveManualExecutions' => false,
                'saveDataSuccessExecution' => 'none',
                'saveExecutionProgress' => false,
            ],
        ];
    }

    private function prepareRequestCode(): string
    {
        $model = json_encode(
            (string) $this->config->get('event_moderation.openrouter.model'),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        $systemPrompt = json_encode(
            $this->systemPrompt(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        $template = <<<'JS'
const incoming = $input.first().json;
const payload = incoming.body ?? incoming;

if (!payload || !Number.isInteger(Number(payload.request_id))) {
  throw new Error('Invalid Scemory moderation payload: request_id is required.');
}

if (!payload.event || !Number.isInteger(Number(payload.event.id))) {
  throw new Error('Invalid Scemory moderation payload: event.id is required.');
}

const systemPrompt = __SYSTEM_PROMPT__;
const userPrompt = [
  'Review this Scemory event submission using only the supplied JSON.',
  'Media URLs and metadata are context only; do not claim visual inspection.',
  'If media content is essential but cannot be verified, choose manual_review.',
  JSON.stringify(payload),
].join('\n\n');

return [{
  json: {
    model: __MODEL__,
    messages: [
      { role: 'system', content: systemPrompt },
      { role: 'user', content: userPrompt },
    ],
    temperature: 0,
    max_completion_tokens: 1200,
    reasoning: {
      enabled: false,
    },
    response_format: {
      type: 'json_schema',
      json_schema: {
        name: 'scemory_event_moderation',
        strict: true,
        schema: {
          type: 'object',
          additionalProperties: false,
          required: ['decision', 'confidence', 'reason', 'flags'],
          properties: {
            decision: {
              type: 'string',
              enum: ['approved', 'rejected', 'manual_review'],
            },
            confidence: {
              type: 'number',
              minimum: 0,
              maximum: 1,
            },
            reason: {
              type: 'string',
              minLength: 1,
              maxLength: 2000,
            },
            flags: {
              type: 'array',
              maxItems: 25,
              items: { type: 'string' },
            },
          },
        },
      },
    },
  },
}];
JS;

        return str_replace(
            ['__MODEL__', '__SYSTEM_PROMPT__'],
            [$model, $systemPrompt],
            $template
        );
    }

    private function parseResponseCode(): string
    {
        return <<<'JS'
const response = $input.first().json;
let content = response?.choices?.[0]?.message?.content;

if (typeof content !== 'string' || content.trim() === '') {
  throw new Error('OpenRouter returned no structured moderation content.');
}

content = content.trim().replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '');

let parsed;
try {
  parsed = JSON.parse(content);
} catch {
  throw new Error('OpenRouter moderation content is not valid JSON.');
}

const allowed = ['approved', 'rejected', 'manual_review'];
if (!allowed.includes(parsed?.decision)) {
  throw new Error('OpenRouter returned an unsupported moderation decision.');
}

if (typeof parsed.confidence !== 'number' || !Number.isFinite(parsed.confidence) || parsed.confidence < 0 || parsed.confidence > 1) {
  throw new Error('OpenRouter returned invalid moderation confidence.');
}

if (typeof parsed.reason !== 'string' || parsed.reason.trim() === '' || parsed.reason.length > 5000) {
  throw new Error('OpenRouter returned an invalid moderation reason.');
}

if (!Array.isArray(parsed.flags) || parsed.flags.some((flag) => typeof flag !== 'string')) {
  throw new Error('OpenRouter returned invalid moderation flags.');
}

return [{
  json: {
    decision: parsed.decision,
    confidence: parsed.confidence,
    reason: parsed.reason.trim(),
    flags: parsed.flags.slice(0, 25),
    workflow_execution_id: String($execution.id),
  },
}];
JS;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the conservative event-publication moderator for Scemory. Analyze only the event data supplied by the application. Never invent missing facts, never reveal chain-of-thought, and return only the required JSON object.

Approve coherent, meaningful event submissions that appear suitable for publication. Reject only clear cases such as obvious spam or garbage, prohibited or harmful material, severe incoherence, clearly impossible or invalid essential dates, unrelated content, or unmistakably fabricated nonsense. Avoid false rejection.

Choose manual_review whenever evidence is incomplete, claims are suspicious but not provably invalid, essential inconsistencies cannot be resolved, media would need visual or video inspection that was not actually performed, or you otherwise cannot make a reliable decision. A concise reason must explain the outcome and be suitable for an administrator; for rejection it may also be sent to the event owner. Flags must be short machine-readable labels. Do not include private analysis.

Return exactly: {"decision":"approved|rejected|manual_review","confidence":0.0,"reason":"concise rationale","flags":[]}.
PROMPT;
    }

    private function openRouterEndpoint(): string
    {
        return rtrim((string) $this->config->get(
            'event_moderation.openrouter.api_url',
            'https://openrouter.ai/api/v1'
        ), '/').'/chat/completions';
    }
}
