<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Defaults keep today's behaviour (everything in the database); Redis
        // is switched on from Settings → Cache, queue & Redis.
        $this->migrator->add('performance.redis_enabled', false);
        $this->migrator->add('performance.redis_client', 'predis');
        $this->migrator->add('performance.redis_scheme', 'tcp');
        $this->migrator->add('performance.redis_host', '127.0.0.1');
        $this->migrator->add('performance.redis_port', 6379);
        $this->migrator->add('performance.redis_username', null);
        $this->migrator->addEncrypted('performance.redis_password', null);
        $this->migrator->add('performance.redis_database', 0);
        $this->migrator->add('performance.redis_cache_database', 1);
        $this->migrator->add('performance.redis_prefix', 'sso:');
        $this->migrator->add('performance.redis_timeout', 1.0);
        $this->migrator->add('performance.cache_store', 'database');
        $this->migrator->add('performance.queue_connection', 'database');
        $this->migrator->add('performance.session_store', 'database');
        $this->migrator->add('performance.redis_fallback', true);
    }
};
