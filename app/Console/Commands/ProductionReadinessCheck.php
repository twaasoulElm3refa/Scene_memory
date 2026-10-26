<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Predis\Client;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Read-only check of the settings production depends on. Prints only states and
 * yes/no answers, never secret values. PayPal has its own command,
 * payments:diagnose-webhooks.
 */
class ProductionReadinessCheck extends Command
{
    protected $signature = 'scemory:production-check {--json : Print the results as JSON}';

    protected $description = 'Check cache, database, PHP, media and queue settings for production without changing anything';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(): int
    {
        $production = app()->environment('production');
        $this->row('app.env', $production ? 'ok' : 'warn', (string) config('app.env'));
        $this->row('app.debug', config('app.debug') ? ($production ? 'fail' : 'warn') : 'ok', config('app.debug') ? 'on' : 'off');
        $this->row('app.key', filled(config('app.key')) ? 'ok' : 'fail', filled(config('app.key')) ? 'set' : 'missing');
        $this->row('app.url', str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'warn', 'https '.(str_starts_with((string) config('app.url'), 'https://') ? 'yes' : 'no'));
        $this->row('log.level', in_array(config('logging.channels.'.config('logging.default').'.level'), ['debug'], true) && $production ? 'warn' : 'ok', (string) (config('logging.channels.'.config('logging.default').'.level') ?? 'default'));

        $this->checkCache($production);
        $this->checkDatabase();
        $this->checkQueueAndSession($production);
        $this->checkMedia();
        $this->checkPhp();

        if ($this->option('json')) {
            $this->line(json_encode(array_map(fn ($row) => ['check' => $row[0], 'status' => $row[1], 'detail' => $row[2]], $this->rows), JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Status', 'Detail'], array_map(fn ($row) => [$row[0], strtoupper($row[1]), $row[2]], $this->rows));
            $this->line('PayPal webhooks: run php artisan payments:diagnose-webhooks');
        }

        return collect($this->rows)->contains(fn ($row) => $row[1] === 'fail') ? self::FAILURE : self::SUCCESS;
    }

    private function checkCache(bool $production): void
    {
        $store = (string) config('cache.default');
        $driver = (string) config("cache.stores.{$store}.driver");
        $this->row('cache.store', in_array($driver, ['redis', 'memcached'], true) ? 'ok' : ($production ? 'fail' : 'warn'), "{$store} ({$driver})");
        try {
            $tags = Cache::supportsTags();
            $key = 'production-check:'.Str::random(16);
            Cache::put($key, 'ok', 30);
            $roundTrip = Cache::get($key) === 'ok';
            Cache::forget($key);
            $this->row('cache.round_trip', $roundTrip ? 'ok' : 'fail', $roundTrip ? 'write and read back' : 'value not returned');
            $this->row('cache.tags', $tags ? 'ok' : ($production ? 'fail' : 'warn'), $tags ? 'supported' : 'unsupported: tagged pages run uncached');
        } catch (Throwable $exception) {
            $this->row('cache.round_trip', 'fail', class_basename($exception));
        }
        $this->row('cache.require_tags', config('cache.require_tags') ? 'ok' : 'warn', config('cache.require_tags') ? 'GET /up fails without tag support' : 'off (set CACHE_REQUIRE_TAGS=true once Redis is confirmed)');
        $needsRedis = in_array('redis', [$driver, config('queue.default'), config('session.driver')], true);
        if ($needsRedis) {
            $client = (string) config('database.redis.client');
            $available = $client === 'phpredis' ? extension_loaded('redis') : class_exists(Client::class);
            $this->row('redis.client', $available ? 'ok' : 'fail', $client.($available ? ' available' : ' missing'));
        }
    }

    private function checkDatabase(): void
    {
        try {
            $connection = DB::connection();
            $connection->select('SELECT 1');
            $driver = $connection->getDriverName();
            $this->row('db.connection', 'ok', $driver);
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $version = (string) ($connection->selectOne('SELECT VERSION() AS v')->v ?? '');
                $isolation = $connection->selectOne("SHOW VARIABLES WHERE Variable_name IN ('transaction_isolation', 'tx_isolation')");
                $lockWait = $connection->selectOne("SHOW VARIABLES LIKE 'innodb_lock_wait_timeout'");
                $maxConnections = $connection->selectOne("SHOW VARIABLES LIKE 'max_connections'");
                $this->row('db.version', 'ok', $version);
                $this->row('db.isolation', 'ok', (string) ($isolation->Value ?? 'unknown'));
                $this->row('db.innodb_lock_wait_timeout', 'ok', (string) ($lockWait->Value ?? 'unknown').' s');
                $this->row('db.max_connections', 'ok', (string) ($maxConnections->Value ?? 'unknown'));
            }
            foreach (['financial_holds', 'wallet_reservation_movements', 'paypal_webhook_events', 'cache', 'jobs', 'failed_jobs'] as $table) {
                $exists = Schema::hasTable($table);
                $required = in_array($table, ['financial_holds', 'wallet_reservation_movements', 'paypal_webhook_events'], true);
                $this->row("db.table.{$table}", $exists ? 'ok' : ($required ? 'fail' : 'warn'), $exists ? 'present' : 'missing (run php artisan migrate)');
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
                $this->row('queue.failed_jobs', $failed === 0 ? 'ok' : 'warn', (string) $failed);
            }
            if (Schema::hasTable('financial_holds')) {
                $this->row('finance.active_holds', 'ok', (string) DB::table('financial_holds')->where('status', 'active')->count());
            }
        } catch (Throwable $exception) {
            $this->row('db.connection', 'fail', class_basename($exception));
        }
    }

    private function checkQueueAndSession(bool $production): void
    {
        $queue = (string) config('queue.default');
        $this->row('queue.connection', $queue === 'sync' && $production ? 'warn' : 'ok', $queue.($queue === 'sync' ? ': uploads process inline' : ''));
        $this->row('session.driver', 'ok', (string) config('session.driver'));
        $mailer = (string) config('mail.default');
        $this->row('mail.mailer', in_array($mailer, ['log', 'array'], true) && $production ? 'warn' : 'ok', $mailer);
    }

    private function checkMedia(): void
    {
        $root = realpath((string) config('filesystems.disks.media.root')) ?: (string) config('filesystems.disks.media.root');
        $public = realpath(public_path()) ?: public_path();
        $insideWebroot = str_starts_with(str_replace('\\', '/', $root).'/', str_replace('\\', '/', $public).'/');
        $this->row('media.private_root', $insideWebroot ? 'fail' : 'ok', $insideWebroot ? 'inside public/' : 'outside public/');
        $this->row('media.public_storage_link', is_link(public_path('storage')) || is_dir(public_path('storage')) ? 'ok' : 'warn', is_link(public_path('storage')) ? 'symlink' : (is_dir(public_path('storage')) ? 'directory' : 'missing'));
        $quarantine = (string) config('filesystems.disks.legacy-quarantine.root');
        $this->row('media.quarantine_disk', $quarantine !== '' && ! str_starts_with(str_replace('\\', '/', $quarantine), str_replace('\\', '/', $public)) ? 'ok' : 'fail', $quarantine !== '' ? 'outside public/' : 'not configured');
        $finder = new ExecutableFinder;
        foreach (['ffmpeg' => config('services.ffmpeg.binary', 'ffmpeg'), 'ffprobe' => config('services.ffmpeg.ffprobe_binary', 'ffprobe')] as $name => $binary) {
            $found = is_file((string) $binary) || $finder->find((string) $binary) !== null;
            $this->row("media.{$name}", $found ? 'ok' : 'warn', $found ? 'found' : 'not found: video uploads cannot be processed');
        }
    }

    private function checkPhp(): void
    {
        $this->row('php.version', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail', PHP_VERSION);
        foreach (['mbstring', 'openssl', 'pdo_mysql', 'fileinfo', 'curl', 'gd'] as $extension) {
            $this->row("php.ext.{$extension}", extension_loaded($extension) ? 'ok' : ($extension === 'gd' ? 'warn' : 'fail'), extension_loaded($extension) ? 'loaded' : 'missing');
        }
        $opcache = function_exists('opcache_get_status') && (bool) ini_get('opcache.enable'.(PHP_SAPI === 'cli' ? '_cli' : ''));
        $this->row('php.opcache', $opcache ? 'ok' : 'warn', ($opcache ? 'enabled' : 'disabled').' for '.PHP_SAPI.'; check PHP-FPM/Apache separately');
        foreach (['memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time'] as $setting) {
            $this->row("php.{$setting}", 'ok', (string) ini_get($setting).' ('.PHP_SAPI.')');
        }
        $upload = $this->bytes((string) ini_get('upload_max_filesize'));
        $this->row('php.upload_vs_rule', $upload === 0 || $upload >= 20460 * 1024 ? 'ok' : 'warn', 'media rule allows 20460 KB per file');
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function row(string $check, string $status, string $detail): void
    {
        $this->rows[] = [$check, $status, $detail];
    }
}
