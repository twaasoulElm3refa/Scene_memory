<?php

namespace App\Services\N8n;

use App\Support\EventTranslationLocales;
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
                    'position' => [-1000, 0],
                    'webhookId' => 'scemory-event-moderation',
                    'credentials' => [
                        self::HEADER_CREDENTIAL_TYPE => $webhookCredential,
                    ],
                ],
                [
                    'parameters' => [
                        'conditions' => [
                            'options' => [
                                'caseSensitive' => true,
                                'leftValue' => '',
                                'typeValidation' => 'strict',
                                'version' => 2,
                            ],
                            'conditions' => [[
                                'id' => 'e727388c-8a9b-4031-a8c2-d18f755201dd',
                                'leftValue' => '={{ (($json.body ?? $json).mode ?? "moderation") }}',
                                'rightValue' => 'translation',
                                'operator' => [
                                    'type' => 'string',
                                    'operation' => 'equals',
                                ],
                            ]],
                            'combinator' => 'and',
                        ],
                        'options' => [],
                    ],
                    'id' => 'bbdc3da3-8c55-4779-a8f4-12dca5e1fd9a',
                    'name' => 'Is Translation Only',
                    'type' => 'n8n-nodes-base.if',
                    'typeVersion' => 2.2,
                    'position' => [-760, 0],
                ],
                [
                    'parameters' => [
                        'jsCode' => $this->prepareRequestCode(),
                    ],
                    'id' => '43c39a77-1144-449d-8821-dd98616032cf',
                    'name' => 'Validate and Build AI Request',
                    'type' => 'n8n-nodes-base.code',
                    'typeVersion' => 2,
                    'position' => [-520, 160],
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
                    'position' => [-280, 160],
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
                    'position' => [-40, 160],
                ],
                [
                    'parameters' => [
                        'conditions' => [
                            'options' => [
                                'caseSensitive' => true,
                                'leftValue' => '',
                                'typeValidation' => 'strict',
                                'version' => 2,
                            ],
                            'conditions' => [[
                                'id' => '3a824610-7bd2-442c-8228-f0184e711ccd',
                                'leftValue' => $this->approvedDecisionExpression(),
                                'rightValue' => 'approved',
                                'operator' => [
                                    'type' => 'string',
                                    'operation' => 'equals',
                                ],
                            ]],
                            'combinator' => 'and',
                        ],
                        'options' => [],
                    ],
                    'id' => 'e065639c-0702-440a-86db-254160bec46a',
                    'name' => 'Is Approved for Publication',
                    'type' => 'n8n-nodes-base.if',
                    'typeVersion' => 2.2,
                    'position' => [200, 160],
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
                    'position' => [440, 300],
                ],
                [
                    'parameters' => [
                        'respondWith' => 'json',
                        'responseBody' => '={{ $json }}',
                        'options' => [
                            'responseCode' => 200,
                        ],
                    ],
                    'id' => '430cc3f3-743a-456a-bfd2-d1a18bf8217a',
                    'name' => 'Return Approval to Laravel',
                    'type' => 'n8n-nodes-base.respondToWebhook',
                    'typeVersion' => 1.4,
                    'position' => [440, 100],
                ],
                [
                    'parameters' => [
                        'respondWith' => 'json',
                        'responseBody' => '{"accepted":true}',
                        'options' => [
                            'responseCode' => 202,
                        ],
                    ],
                    'id' => '44013d8f-dd48-4be2-b941-893d19ffcd6f',
                    'name' => 'Return Translation Request Accepted',
                    'type' => 'n8n-nodes-base.respondToWebhook',
                    'typeVersion' => 1.4,
                    'position' => [-520, -200],
                ],
                [
                    'parameters' => [
                        'jsCode' => $this->prepareTranslationRequestCode(),
                    ],
                    'id' => 'e6720ddd-6011-48b3-a89e-10d196a73069',
                    'name' => 'Build Translation Request',
                    'type' => 'n8n-nodes-base.code',
                    'typeVersion' => 2,
                    'position' => [700, -80],
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
                            'timeout' => 120000,
                        ],
                    ],
                    'id' => '1b26e953-7b3c-4946-b244-e742935b94f6',
                    'name' => 'OpenRouter Translation',
                    'type' => 'n8n-nodes-base.httpRequest',
                    'typeVersion' => 4.2,
                    'position' => [960, -80],
                    'credentials' => [
                        self::HEADER_CREDENTIAL_TYPE => $openRouterCredential,
                    ],
                ],
                [
                    'parameters' => [
                        'jsCode' => $this->parseTranslationsCode(),
                    ],
                    'id' => 'ffb6ef2c-63ca-49bc-9836-876b02578a31',
                    'name' => 'Parse and Validate Translations',
                    'type' => 'n8n-nodes-base.code',
                    'typeVersion' => 2,
                    'position' => [1220, -80],
                ],
                [
                    'parameters' => [
                        'method' => 'POST',
                        'url' => $this->translationCallbackUrlExpression(),
                        'authentication' => 'genericCredentialType',
                        'genericAuthType' => self::HEADER_CREDENTIAL_TYPE,
                        'sendBody' => true,
                        'specifyBody' => 'json',
                        'jsonBody' => '={{ $json }}',
                        'options' => [
                            'timeout' => 30000,
                        ],
                    ],
                    'id' => '25020778-e67e-4c04-87aa-c1b28ea813fe',
                    'name' => 'Store Translations in Laravel',
                    'type' => 'n8n-nodes-base.httpRequest',
                    'typeVersion' => 4.2,
                    'position' => [1480, -80],
                    'credentials' => [
                        self::HEADER_CREDENTIAL_TYPE => $webhookCredential,
                    ],
                ],
            ],
            'connections' => [
                'Scemory Moderation Webhook' => [
                    'main' => [[[
                        'node' => 'Is Translation Only',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'Is Translation Only' => [
                    'main' => [
                        [[
                            'node' => 'Return Translation Request Accepted',
                            'type' => 'main',
                            'index' => 0,
                        ]],
                        [[
                            'node' => 'Validate and Build AI Request',
                            'type' => 'main',
                            'index' => 0,
                        ]],
                    ],
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
                        'node' => 'Is Approved for Publication',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'Is Approved for Publication' => [
                    'main' => [
                        [[
                            'node' => 'Return Approval to Laravel',
                            'type' => 'main',
                            'index' => 0,
                        ]],
                        [[
                            'node' => 'Return Decision to Laravel',
                            'type' => 'main',
                            'index' => 0,
                        ]],
                    ],
                ],
                'Return Approval to Laravel' => [
                    'main' => [[[
                        'node' => 'Build Translation Request',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'Return Translation Request Accepted' => [
                    'main' => [[[
                        'node' => 'Build Translation Request',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'Build Translation Request' => [
                    'main' => [[[
                        'node' => 'OpenRouter Translation',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'OpenRouter Translation' => [
                    'main' => [[[
                        'node' => 'Parse and Validate Translations',
                        'type' => 'main',
                        'index' => 0,
                    ]]],
                ],
                'Parse and Validate Translations' => [
                    'main' => [[[
                        'node' => 'Store Translations in Laravel',
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

    private function approvedDecisionExpression(): string
    {
        $threshold = min(1, max(0, (float) $this->config->get(
            'event_moderation.auto_decision_threshold',
            0.85
        )));

        return sprintf(
            '={{ ($json.decision === "approved" && Number($json.confidence) >= %s) ? "approved" : "not_approved" }}',
            json_encode($threshold, JSON_THROW_ON_ERROR)
        );
    }

    private function prepareTranslationRequestCode(): string
    {
        $model = json_encode(
            (string) $this->config->get('event_moderation.openrouter.translation_model'),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        $locales = json_encode(
            EventTranslationLocales::ALL,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
        $systemPrompt = json_encode(
            $this->translationSystemPrompt(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        $maxTokens = max(2000, (int) $this->config->get(
            'event_moderation.openrouter.translation_max_tokens',
            12000
        ));
        $template = <<<'JS'
const incoming = $('Scemory Moderation Webhook').first().json;
const payload = incoming.body ?? incoming;
const event = payload?.event;

if (!event || !Number.isInteger(Number(event.id))) {
  throw new Error('Invalid Scemory translation payload: event.id is required.');
}

if (typeof event.title !== 'string' || typeof event.description !== 'string') {
  throw new Error('Invalid Scemory translation payload: title and description are required.');
}

const locales = __LOCALES__;
const userPrompt = [
  'Original language: Arabic',
  `Required locales: ${locales.join(', ')}`,
  'For ar, return the original title and description unchanged.',
  'Translate both fields for every other locale.',
  '',
  'Title:',
  event.title,
  '',
  'Description:',
  event.description,
].join('\n');

return [{
  json: {
    model: __MODEL__,
    messages: [
      { role: 'system', content: __SYSTEM_PROMPT__ },
      { role: 'user', content: userPrompt },
    ],
    temperature: 0,
    max_completion_tokens: __MAX_TOKENS__,
    reasoning: {
      enabled: false,
    },
    response_format: {
      type: 'json_schema',
      json_schema: {
        name: 'scemory_event_translations',
        strict: true,
        schema: {
          type: 'object',
          additionalProperties: false,
          required: ['translations'],
          properties: {
            translations: {
              type: 'array',
              minItems: 13,
              maxItems: 13,
              items: {
                type: 'object',
                additionalProperties: false,
                required: ['locale', 'title', 'description'],
                properties: {
                  locale: { type: 'string', enum: locales },
                  title: { type: 'string', minLength: 1 },
                  description: { type: 'string', minLength: 1 },
                },
              },
            },
          },
        },
      },
    },
  },
}];
JS;

        return str_replace(
            ['__MODEL__', '__LOCALES__', '__SYSTEM_PROMPT__', '__MAX_TOKENS__'],
            [$model, $locales, $systemPrompt, (string) $maxTokens],
            $template
        );
    }

    private function parseTranslationsCode(): string
    {
        $locales = json_encode(
            EventTranslationLocales::ALL,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
        $template = <<<'JS'
const response = $input.first().json;
let content = response?.choices?.[0]?.message?.content;

if (typeof content !== 'string' || content.trim() === '') {
  throw new Error('OpenRouter returned no structured translation content.');
}

content = content.trim().replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '');

let parsed;
try {
  parsed = JSON.parse(content);
} catch {
  throw new Error('OpenRouter translation content is not valid JSON.');
}

const expectedLocales = __LOCALES__;
if (!Array.isArray(parsed?.translations) || parsed.translations.length !== expectedLocales.length) {
  throw new Error('OpenRouter must return exactly 13 event translations.');
}

const translationsByLocale = new Map();
for (const translation of parsed.translations) {
  if (!translation || !expectedLocales.includes(translation.locale)) {
    throw new Error('OpenRouter returned an unsupported translation locale.');
  }

  if (translationsByLocale.has(translation.locale)) {
    throw new Error('OpenRouter returned a duplicate translation locale.');
  }

  if (typeof translation.title !== 'string' || translation.title.trim() === '') {
    throw new Error(`OpenRouter returned an invalid title for ${translation.locale}.`);
  }

  if (typeof translation.description !== 'string' || translation.description.trim() === '') {
    throw new Error(`OpenRouter returned an invalid description for ${translation.locale}.`);
  }

  translationsByLocale.set(translation.locale, {
    locale: translation.locale,
    title: translation.title.trim(),
    description: translation.description.trim(),
  });
}

const incoming = $('Scemory Moderation Webhook').first().json;
const payload = incoming.body ?? incoming;
const event = payload?.event;
const arabic = translationsByLocale.get('ar');

if (!event || !arabic || arabic.title !== event.title || arabic.description !== event.description) {
  throw new Error('The Arabic translation must exactly preserve the original event.');
}

return [{
  json: {
    event_id: Number(event.id),
    translations: expectedLocales.map((locale) => translationsByLocale.get(locale)),
  },
}];
JS;

        return str_replace('__LOCALES__', $locales, $template);
    }

    private function translationSystemPrompt(): string
    {
        return <<<'PROMPT'
You translate Scemory event content from Arabic. Translate accurately and literally; do not summarize, rewrite creatively, add facts, remove facts, or change meaning. Preserve all dates, numbers, coordinates, measurements, magnitudes, locations, factual names, and proper nouns. Transliterate proper names only when appropriate for the target language. Return only the required strict JSON object with all requested locales. For ar, copy the original Arabic title and description exactly unchanged.
PROMPT;
    }

    private function translationCallbackUrlExpression(): string
    {
        $baseUrl = rtrim((string) $this->config->get(
            'event_moderation.laravel.base_url',
            $this->config->get('app.url')
        ), '/');
        $path = trim((string) $this->config->get(
            'event_moderation.laravel.translation_path',
            'api/v1/moderation/events'
        ), '/');
        $prefix = json_encode(
            "{$baseUrl}/{$path}/",
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        return '={{ '.$prefix.' + $json.event_id + "/translations" }}';
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
