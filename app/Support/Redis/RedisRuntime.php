<?php

namespace App\Support\Redis;

use App\Domain\Settings\PerformanceSettings;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies Settings → Cache, queue & Redis to the running application and
 * talks to the Redis server for the admin console and `sso:check`.
 *
 * Fail-safe: when Redis cannot be reached (and fallback is on) the cache and
 * queue quietly use the database instead, so a stopped Redis service never
 * takes sign-in down. A marker file stops every request from paying the
 * connect timeout again; Redis is retried after RETRY_SECONDS.
 */
final class RedisRuntime
{
    public const MARKER = 'framework/redis-unavailable';

    public const RETRY_SECONDS = 30;

    /** Effective stores after fallback, for the admin console. */
    public static array $effective = [];

    private static bool $redisEnabled = false;

    public static function apply(PerformanceSettings $settings): void
    {
        self::$redisEnabled = $settings->redis_enabled;

        config([
            'database.redis.client' => self::client($settings->redis_client),
            'database.redis.options.prefix' => $settings->redis_prefix,
            'database.redis.options.persistent' => false,
            'database.redis.default' => self::connection($settings, $settings->redis_database),
            'database.redis.cache' => self::connection($settings, $settings->redis_cache_database),
            'cache.stores.redis.connection' => 'cache',
            'cache.stores.redis.lock_connection' => 'default',
            'queue.connections.redis.connection' => 'default',
            'queue.connections.redis.queue' => 'default',
        ]);

        // Redis config changed: make sure nothing holds a manager built from the old values.
        app()->forgetInstance('redis');
        app()->forgetInstance('redis.connection');
        Facade::clearResolvedInstance('redis');

        $redis = $settings->usesRedis() && (! $settings->redis_fallback || self::reachable());
        $pick = fn (string $wanted, string $fallback) => $wanted === 'redis' && ! $redis ? $fallback : $wanted;

        self::$effective = [
            'cache' => $pick($settings->cache_store, 'database'),
            'queue' => $pick($settings->queue_connection, 'database'),
            'session' => $pick($settings->session_store, 'database'),
        ];

        config([
            'cache.default' => self::$effective['cache'],
            'queue.default' => self::$effective['queue'],
            'session.driver' => self::$effective['session'],
        ]);

        // session.connection names a *database* connection for the database
        // driver and a Redis connection for the redis driver.
        if (self::$effective['session'] === 'redis') {
            config(['session.connection' => 'default']);
        }
    }

    /**
     * Queue connections that are not the default but may still hold jobs.
     *
     * @return array<int, string>
     */
    public static function idleQueues(): array
    {
        $queues = [];
        $default = config('queue.default');

        if ($default !== 'database') {
            $queues[] = 'database';
        }

        if ($default !== 'redis' && self::$redisEnabled && ! self::fallbackActive()) {
            $queues[] = 'redis';
        }

        return $queues;
    }

    /** True while the SSO runs on the database because Redis was unreachable. */
    public static function fallbackActive(): bool
    {
        $marker = storage_path(self::MARKER);

        return is_file($marker) && filemtime($marker) > time() - self::RETRY_SECONDS;
    }

    /**
     * Connects with the given (possibly unsaved) values and reports on the server.
     *
     * @param  array<string, mixed>  $values  PerformanceSettings property names
     * @return array<string, mixed>
     */
    public static function probe(array $values): array
    {
        $settings = new class
        {
            public string $redis_scheme = 'tcp';

            public string $redis_host = '127.0.0.1';

            public int $redis_port = 6379;

            public ?string $redis_username = null;

            public ?string $redis_password = null;

            public float $redis_timeout = 1.0;
        };

        foreach ($values as $key => $value) {
            if (property_exists($settings, $key) && $value !== null && $value !== '') {
                $settings->{$key} = match ($key) {
                    'redis_port' => (int) $value,
                    'redis_timeout' => (float) $value,
                    default => (string) $value,
                };
            }
        }

        $client = self::client((string) ($values['redis_client'] ?? 'predis'));
        $databases = array_unique([(int) ($values['redis_database'] ?? 0), (int) ($values['redis_cache_database'] ?? 1)]);

        $manager = new RedisManager(app(), $client, [
            'options' => ['prefix' => (string) ($values['redis_prefix'] ?? '')],
            'probe' => self::connection($settings, $databases[0], retries: 0),
        ]);

        try {
            $connection = $manager->connection('probe');
            $connection->command('ping'); // opens the connection; time the next one

            $started = hrtime(true);
            $connection->command('ping');
            $latency = (hrtime(true) - $started) / 1e6;

            $info = self::flatten((array) $connection->command('info'));
            $config = [];

            foreach (['maxmemory', 'maxmemory-policy', 'appendonly', 'save', 'bind', 'protected-mode'] as $name) {
                try {
                    $config += (array) $connection->command('config', ['GET', $name]);
                } catch (Throwable) {
                    // CONFIG may be renamed or forbidden for this user.
                }
            }

            $keys = [];
            foreach ($databases as $db) {
                $keys[$db] = self::keyCount($info['db'.$db] ?? null);
            }

            $connection->disconnect();

            return [
                'ok' => true,
                'client' => $client,
                'latency_ms' => round($latency, 2),
                'version' => $info['redis_version'] ?? '?',
                'os' => $info['os'] ?? '',
                'uptime_seconds' => (int) ($info['uptime_in_seconds'] ?? 0),
                'used_memory' => $info['used_memory_human'] ?? '?',
                'connected_clients' => (int) ($info['connected_clients'] ?? 0),
                'maxmemory' => (int) ($config['maxmemory'] ?? 0),
                'policy' => $config['maxmemory-policy'] ?? null,
                'appendonly' => $config['appendonly'] ?? null,
                'save' => $config['save'] ?? null,
                'bind' => $config['bind'] ?? null,
                'keys' => $keys,
                'warnings' => self::warnings($config, $values),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'client' => $client, 'error' => $e->getMessage()];
        }
    }

    /**
     * Advice about the Redis server's own configuration (redis.conf).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private static function warnings(array $config, array $values): array
    {
        $warnings = [];
        $queue = ($values['queue_connection'] ?? null) === 'redis';

        if (array_key_exists('maxmemory', $config) && (int) $config['maxmemory'] === 0) {
            $warnings[] = 'No memory limit (maxmemory 0): set “maxmemory 256mb” in redis.conf so Redis cannot starve the server.';
        }

        $policy = $config['maxmemory-policy'] ?? null;
        if ($policy !== null && $queue && str_starts_with($policy, 'allkeys')) {
            $warnings[] = "Eviction policy {$policy} can delete queued jobs: use “maxmemory-policy volatile-lru” (only keys with an expiry are evicted).";
        } elseif ($policy === 'noeviction') {
            $warnings[] = 'Eviction policy noeviction: when memory is full, writes fail. “volatile-lru” lets Redis drop old cache entries while keeping queued jobs.';
        }

        if ($queue && ($config['appendonly'] ?? null) === 'no') {
            $warnings[] = 'Persistence is off (appendonly no): queued emails and logout notices are lost if Redis restarts. Set “appendonly yes” and “appendfsync everysec”.';
        }

        if (blank($values['redis_password'] ?? null) && ! str_contains((string) ($config['bind'] ?? '127.0.0.1'), '0.0.0.0')) {
            $warnings[] = 'No password. Acceptable while Redis only listens on 127.0.0.1; set “requirepass” before it ever listens on the network.';
        } elseif (blank($values['redis_password'] ?? null)) {
            $warnings[] = 'Redis listens on all interfaces without a password: anyone on the network can read sessions and cached data.';
        }

        return $warnings;
    }

    private static function reachable(): bool
    {
        if (self::fallbackActive()) {
            return false;
        }

        $marker = storage_path(self::MARKER);

        try {
            app('redis')->connection('default')->command('ping');

            if (is_file($marker)) {
                @unlink($marker);
                Log::info('Redis is reachable again; cache and queue are back on Redis.');
            }

            return true;
        } catch (Throwable $e) {
            if (! is_file($marker)) {
                Log::error('Redis is unreachable; using the database for cache and queue until it is back.', ['error' => $e->getMessage()]);
            }

            @touch($marker);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function connection(object $settings, int $database, int $retries = 1): array
    {
        return [
            'url' => null,
            'scheme' => $settings->redis_scheme,
            'host' => $settings->redis_host,
            'port' => $settings->redis_port,
            'username' => blank($settings->redis_username) ? null : $settings->redis_username,
            'password' => blank($settings->redis_password) ? null : $settings->redis_password,
            'database' => $database,
            'timeout' => $settings->redis_timeout,
            'read_timeout' => 5,
            'read_write_timeout' => 5,
            'max_retries' => $retries,
            'backoff_algorithm' => 'decorrelated_jitter',
            'backoff_base' => 100,
            'backoff_cap' => 500,
        ];
    }

    public static function client(string $wanted): string
    {
        return $wanted === 'phpredis' && extension_loaded('redis') ? 'phpredis' : 'predis';
    }

    /**
     * predis returns INFO grouped by section, phpredis flat.
     *
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private static function flatten(array $info): array
    {
        $flat = [];

        foreach ($info as $key => $value) {
            if (is_array($value) && ! preg_match('/^db\d+$/', (string) $key)) {
                $flat += self::flatten($value);
            } else {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }

    private static function keyCount(mixed $keyspace): int
    {
        if (is_array($keyspace)) {
            return (int) ($keyspace['keys'] ?? 0);
        }

        return preg_match('/keys=(\d+)/', (string) $keyspace, $m) ? (int) $m[1] : 0;
    }
}
