<?php

namespace App\Console\Commands;

use App\Models\PaypalWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DiagnosePayPalWebhooks extends Command
{
    protected $signature = 'payments:diagnose-webhooks';

    protected $description = 'Diagnose PayPal webhook delivery readiness without exposing secrets';

    public function handle(): int
    {
        $purchaseRoute = Route::getRoutes()->getByName('paypal.webhook');
        $walletRoute = Route::getRoutes()->getByName('wallet.webhook');

        $rows = [
            ['purchase_route_exists', $this->yesNo($purchaseRoute !== null)],
            ['purchase_route_is_post', $this->yesNo($purchaseRoute && in_array('POST', $purchaseRoute->methods(), true))],
            ['purchase_route_has_auth_sanctum', $this->yesNo($this->routeHasMiddleware($purchaseRoute, 'auth:sanctum'))],
            ['wallet_route_exists', $this->yesNo($walletRoute !== null)],
            ['wallet_route_is_post', $this->yesNo($walletRoute && in_array('POST', $walletRoute->methods(), true))],
            ['wallet_route_has_auth_sanctum', $this->yesNo($this->routeHasMiddleware($walletRoute, 'auth:sanctum'))],
            ['app_url_https', $this->yesNo(parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https')],
            ['verify_webhooks_enabled', $this->yesNo((bool) config('paypal.verify_webhooks', true))],
            ['local_bypass_enabled', $this->yesNo((bool) config('paypal.allow_local_webhook_bypass', false))],
            ['purchase_webhook_id_configured', $this->yesNo(filled(config('paypal.webhook_id')))],
            ['wallet_webhook_id_configured', $this->yesNo(filled(config('paypal.wallet_webhook_id')))],
            ['merchant_id_configured', $this->yesNo(filled(config('paypal.merchant_id')))],
            ['paypal_webhook_events_table_exists', $this->yesNo(Schema::hasTable('paypal_webhook_events'))],
            ['payments_table_exists', $this->yesNo(Schema::hasTable('payments'))],
            ['queue_connection', (string) config('queue.default')],
            ['cache_store', (string) config('cache.default')],
            ['redis_reachable', $this->yesNo($this->redisReachable())],
        ];

        if (Schema::hasTable('paypal_webhook_events')) {
            $rows[] = ['recent_webhook_events_24h', (string) PaypalWebhookEvent::where('created_at', '>=', now()->subDay())->count()];
            $rows[] = ['recent_failed_webhook_events_24h', (string) PaypalWebhookEvent::where('created_at', '>=', now()->subDay())->where('status', 'failed')->count()];
            $rows[] = ['latest_webhook_event_status', (string) (PaypalWebhookEvent::latest('id')->value('status') ?? 'none')];
        }

        $this->table(['check', 'result'], $rows);

        return self::SUCCESS;
    }

    private function routeHasMiddleware($route, string $middleware): bool
    {
        return $route && in_array($middleware, $route->gatherMiddleware(), true);
    }

    private function redisReachable(): bool
    {
        try {
            $host = (string) config('database.redis.default.host', '127.0.0.1');
            if ($host === 'redis' && ! file_exists('/.dockerenv')) {
                return false;
            }

            $previousTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', '2');
            if (! extension_loaded('redis') && class_exists(Redis::class) === false) {
                return false;
            }

            $pong = strtoupper((string) Redis::connection()->ping()) === 'PONG';
            ini_set('default_socket_timeout', (string) $previousTimeout);

            return $pong;
        } catch (\Throwable) {
            if (isset($previousTimeout)) {
                ini_set('default_socket_timeout', (string) $previousTimeout);
            }

            try {
                Cache::store('redis')->get('scemory_webhook_diagnose_probe');
                return true;
            } catch (\Throwable) {
                return false;
            }
        }
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
