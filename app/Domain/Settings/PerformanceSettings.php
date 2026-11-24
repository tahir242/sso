<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Where the SSO keeps its cache, queued jobs and sessions, and how it reaches
 * Redis. Applied at boot by App\Support\Redis\RedisRuntime.
 */
class PerformanceSettings extends Settings
{
    /** Connect to Redis at all. When off, every store below falls back to the database. */
    public bool $redis_enabled;

    /** predis (pure PHP, always available) or phpredis (C extension, faster when installed). */
    public string $redis_client;

    /** tcp or tls. */
    public string $redis_scheme;

    public string $redis_host;

    public int $redis_port;

    /** Redis 6+ ACL user; empty for the default user. */
    public ?string $redis_username;

    public ?string $redis_password;

    /** Logical database for queued jobs (and locks). */
    public int $redis_database;

    /** Logical database for the cache, so flushing the cache never drops queued jobs. */
    public int $redis_cache_database;

    /** Prefix for every key, so other applications can share the same Redis server. */
    public string $redis_prefix;

    /** Connect timeout in seconds. */
    public float $redis_timeout;

    /** database | redis | file */
    public string $cache_store;

    /** database | redis */
    public string $queue_connection;

    /** database | redis */
    public string $session_store;

    /** If Redis is unreachable, keep working on the database instead of failing every request. */
    public bool $redis_fallback;

    public static function group(): string
    {
        return 'performance';
    }

    public static function encrypted(): array
    {
        return ['redis_password'];
    }

    public function usesRedis(): bool
    {
        return $this->redis_enabled
            && ($this->cache_store === 'redis' || $this->queue_connection === 'redis' || $this->session_store === 'redis');
    }
}
