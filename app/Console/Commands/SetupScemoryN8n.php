<?php

namespace App\Console\Commands;

use App\Services\EventModeration\N8nEventModerationClient;
use App\Services\EventModeration\WebhookSecretResolver;
use App\Services\N8n\EventModerationWorkflowBuilder;
use App\Services\N8n\N8nApiClient;
use App\Services\N8n\N8nApiException;
use Illuminate\Console\Command;
use Throwable;

class SetupScemoryN8n extends Command
{
    protected $signature = 'scemory:setup-n8n';

    protected $description = 'Create or update and activate the Scemory event moderation workflow in n8n';

    public function handle(
        N8nApiClient $n8n,
        EventModerationWorkflowBuilder $builder,
        N8nEventModerationClient $webhookClient,
        WebhookSecretResolver $secretResolver,
    ): int {
        $this->newLine();
        $this->info('Scemory n8n Setup');
        $this->newLine();

        try {
            $errors = $this->configurationErrors($secretResolver);

            if ($errors !== []) {
                $this->error('Checking configuration............. FAILED');

                foreach ($errors as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }

            $this->line('Checking configuration............. OK');
            $n8n->testConnection();
            $this->line('Connecting to n8n.................. OK');

            $workflowName = (string) config('event_moderation.n8n.workflow_name');
            $matchingWorkflows = collect($n8n->workflows())
                ->filter(fn (array $workflow): bool => ($workflow['name'] ?? null) === $workflowName)
                ->values();

            if ($matchingWorkflows->count() > 1) {
                $this->warn('Multiple workflows already use the configured name; the oldest match will be updated.');
            }

            $existingSummary = $matchingWorkflows
                ->sortBy(fn (array $workflow) => (string) ($workflow['createdAt'] ?? $workflow['id'] ?? ''))
                ->first();
            $existingWorkflow = isset($existingSummary['id'])
                ? $n8n->workflow((string) $existingSummary['id'])
                : null;

            $this->line('Finding workflow................... OK');

            $n8n->credentialSchema(EventModerationWorkflowBuilder::HEADER_CREDENTIAL_TYPE);
            $credentials = $n8n->credentialsIfSupported();
            $webhookCredential = $this->ensureCredential(
                n8n: $n8n,
                availableCredentials: $credentials,
                existingWorkflow: $existingWorkflow,
                nodeName: 'Scemory Moderation Webhook',
                name: EventModerationWorkflowBuilder::WEBHOOK_CREDENTIAL_NAME,
                headerName: 'X-Scemory-Webhook-Secret',
                headerValue: $secretResolver->resolve(),
            );
            $openRouterCredential = $this->ensureCredential(
                n8n: $n8n,
                availableCredentials: $credentials,
                existingWorkflow: $existingWorkflow,
                nodeName: 'OpenRouter Moderation',
                name: EventModerationWorkflowBuilder::OPENROUTER_CREDENTIAL_NAME,
                headerName: 'Authorization',
                headerValue: 'Bearer '.config('event_moderation.openrouter.api_key'),
            );

            $workflowPayload = $builder->build(
                $webhookCredential,
                $openRouterCredential
            );
            $this->line('Building workflow.................. OK');

            $savedWorkflow = $existingWorkflow === null
                ? $n8n->createWorkflow($workflowPayload)
                : $n8n->updateWorkflow(
                    (string) $existingWorkflow['id'],
                    $workflowPayload
                );

            $workflowId = trim((string) ($savedWorkflow['id'] ?? $existingWorkflow['id'] ?? ''));

            if ($workflowId === '') {
                throw new N8nApiException('n8n did not return a workflow ID.');
            }

            $this->line('Updating/creating workflow......... OK');

            if (! (bool) ($savedWorkflow['active'] ?? false)) {
                $n8n->activateWorkflow($workflowId);
            }

            $savedWorkflow = $n8n->workflow($workflowId);
            $active = (bool) ($savedWorkflow['active'] ?? false);
            $this->line('Activating workflow............... '.($active ? 'OK' : 'FAILED'));

            if (! $active) {
                throw new N8nApiException('n8n returned the workflow as inactive after activation.');
            }

            $webhookUrl = $webhookClient->webhookUrl();
            $this->line('Resolving production webhook...... OK');
            $this->newLine();
            $this->info('Scemory Event Moderation is ready.');
            $this->newLine();
            $this->table(['Setting', 'Value'], [
                ['Workflow', $workflowName],
                ['Workflow ID', $workflowId],
                ['Webhook', $webhookUrl],
                ['Active', 'yes'],
            ]);
            $this->newLine();
            $this->info('Done.');

            return self::SUCCESS;
        } catch (N8nApiException $exception) {
            $this->error($exception->getMessage());
            $this->line('No API keys or webhook secrets were printed.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('n8n setup failed: '.$exception->getMessage());
            $this->line('No API keys or webhook secrets were printed.');

            return self::FAILURE;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $availableCredentials
     * @param  array<string, mixed>|null  $existingWorkflow
     * @return array{id: string, name: string}
     */
    private function ensureCredential(
        N8nApiClient $n8n,
        ?array $availableCredentials,
        ?array $existingWorkflow,
        string $nodeName,
        string $name,
        string $headerName,
        string $headerValue,
    ): array {
        $type = EventModerationWorkflowBuilder::HEADER_CREDENTIAL_TYPE;
        $workflowCredential = $this->workflowCredential(
            $existingWorkflow,
            $nodeName,
            $type
        );
        $listedCredential = collect($availableCredentials ?? [])
            ->first(fn (array $credential): bool => ($credential['name'] ?? null) === $name
                && ($credential['type'] ?? null) === $type
            );
        $credential = $workflowCredential ?? $listedCredential;

        if (isset($credential['id']) && $availableCredentials !== null) {
            $updated = $n8n->updateCredential(
                (string) $credential['id'],
                $name,
                $type,
                ['name' => $headerName, 'value' => $headerValue]
            );

            return $this->credentialReference($updated, $name);
        }

        if (isset($credential['id'])) {
            $this->warn("This n8n version cannot list/update credentials; reusing credential '{$name}'.");

            return [
                'id' => (string) $credential['id'],
                'name' => (string) ($credential['name'] ?? $name),
            ];
        }

        $created = $n8n->createCredential(
            $name,
            $type,
            ['name' => $headerName, 'value' => $headerValue]
        );

        return $this->credentialReference($created, $name);
    }

    /**
     * @param  array<string, mixed>|null  $workflow
     * @return array{id?: mixed, name?: mixed}|null
     */
    private function workflowCredential(
        ?array $workflow,
        string $nodeName,
        string $credentialType
    ): ?array {
        foreach ($workflow['nodes'] ?? [] as $node) {
            if (($node['name'] ?? null) !== $nodeName) {
                continue;
            }

            $credential = data_get($node, "credentials.{$credentialType}");

            return is_array($credential) ? $credential : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $credential
     * @return array{id: string, name: string}
     */
    private function credentialReference(array $credential, string $fallbackName): array
    {
        $id = trim((string) ($credential['id'] ?? data_get($credential, 'data.id') ?? ''));

        if ($id === '') {
            throw new N8nApiException('n8n did not return a credential ID.');
        }

        return [
            'id' => $id,
            'name' => (string) ($credential['name'] ?? $fallbackName),
        ];
    }

    /** @return array<int, string> */
    private function configurationErrors(WebhookSecretResolver $secretResolver): array
    {
        $errors = [];
        $required = [
            'N8N_BASE_URL' => config('event_moderation.n8n.base_url'),
            'N8N_API_KEY' => config('event_moderation.n8n.api_key'),
            'OPENROUTER_API_KEY' => config('event_moderation.openrouter.api_key'),
            'OPENROUTER_MODERATION_MODEL' => config('event_moderation.openrouter.model'),
            'OPENROUTER_TRANSLATION_MODEL' => config('event_moderation.openrouter.translation_model'),
            'N8N_LARAVEL_CALLBACK_BASE_URL' => config('event_moderation.laravel.base_url'),
            'SCEMORY_ADMIN_EMAIL' => config('event_moderation.admin_email'),
        ];

        foreach ($required as $name => $value) {
            if (trim((string) $value) === '') {
                $errors[] = "{$name} is required.";
            }
        }

        $baseUrl = (string) config('event_moderation.n8n.base_url');
        $openRouterUrl = (string) config('event_moderation.openrouter.api_url');
        $adminEmail = (string) config('event_moderation.admin_email');
        $laravelCallbackUrl = (string) config('event_moderation.laravel.base_url');
        $threshold = (float) config('event_moderation.auto_decision_threshold');

        if ($baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'N8N_BASE_URL must be a valid URL.';
        }

        if ($openRouterUrl !== '' && filter_var($openRouterUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'OPENROUTER_API_URL must be a valid URL.';
        }

        if ($laravelCallbackUrl !== '' && filter_var($laravelCallbackUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'N8N_LARAVEL_CALLBACK_BASE_URL must be a valid URL.';
        }

        try {
            $secretResolver->resolve();
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'SCEMORY_ADMIN_EMAIL must be a valid email address.';
        }

        if ($threshold < 0 || $threshold > 1) {
            $errors[] = 'AI_EVENT_MODERATION_AUTO_DECISION_THRESHOLD must be between 0 and 1.';
        }

        return $errors;
    }
}
