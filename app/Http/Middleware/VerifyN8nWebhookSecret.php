<?php

namespace App\Http\Middleware;

use App\Services\EventModeration\WebhookSecretResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyN8nWebhookSecret
{
    public function __construct(private readonly WebhookSecretResolver $secretResolver) {}

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        try {
            $expected = $this->secretResolver->resolve();
        } catch (Throwable) {
            return response()->json([
                'message' => 'n8n webhook authentication is not configured.',
            ], 503);
        }

        $provided = trim((string) $request->header('X-Scemory-Webhook-Secret'));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
