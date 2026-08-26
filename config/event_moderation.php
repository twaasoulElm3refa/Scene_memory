<?php

return [
    'enabled' => (bool) env('AI_EVENT_MODERATION_ENABLED', true),
    'auto_decision_threshold' => (float) env('AI_EVENT_MODERATION_AUTO_DECISION_THRESHOLD', 0.85),
    'queue' => env('AI_EVENT_MODERATION_QUEUE', 'default'),
    'overlap_expire_after' => (int) env('AI_EVENT_MODERATION_OVERLAP_EXPIRE_AFTER', 300),
    'admin_email' => env('SCEMORY_ADMIN_EMAIL'),

    'n8n' => [
        'base_url' => env('N8N_BASE_URL'),
        'api_key' => env('N8N_API_KEY'),
        'api_timeout' => (int) env('N8N_API_TIMEOUT', 30),
        'webhook_timeout' => (int) env('N8N_EVENT_MODERATION_WEBHOOK_TIMEOUT', 75),
        'webhook_secret' => env('N8N_EVENT_MODERATION_WEBHOOK_SECRET'),
        'workflow_name' => env('N8N_EVENT_MODERATION_WORKFLOW_NAME', 'Scemory - Event Moderation'),
        'webhook_path' => env('N8N_EVENT_MODERATION_WEBHOOK_PATH', 'scemory-event-moderation'),
        'webhook_url' => env('N8N_EVENT_MODERATION_WEBHOOK_URL'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_N8N_API_KEY'),
        'api_url' => env('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODERATION_MODEL', env('OPENROUTER_MODEL', 'openai/gpt-4.1-mini')),
        'translation_model' => env(
            'OPENROUTER_TRANSLATION_MODEL',
            env('OPENROUTER_MODERATION_MODEL', env('OPENROUTER_MODEL', 'openai/gpt-4.1-mini'))
        ),
        'translation_max_tokens' => (int) env('OPENROUTER_TRANSLATION_MAX_TOKENS', 12000),
    ],

    'laravel' => [
        'base_url' => env('N8N_LARAVEL_CALLBACK_BASE_URL', env('APP_URL')),
        'translation_path' => env(
            'N8N_EVENT_TRANSLATIONS_PATH',
            'api/v1/moderation/events'
        ),
    ],
];
