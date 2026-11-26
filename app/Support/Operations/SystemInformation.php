<?php

namespace App\Support\Operations;

use App\Domain\Settings\PerformanceSettings;
use App\Support\Redis\RedisRuntime;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

/**
 * Facts about the server, PHP, Laravel, the database and background work, for
 * Settings → System information. Read in the web request, so it shows the PHP
 * that Apache runs (not the command line's). Never includes secrets.
 *
 * Each section is a list of rows: label, value and an optional status
 * (ok | warn | fail) with a hint saying what to do.
 */
final class SystemInformation
{
    /** PHP extensions the SSO needs (install.cmd checks the first three). */
    public const array REQUIRED_EXTENSIONS = ['pdo_sqlsrv', 'ldap', 'sodium', 'openssl', 'mbstring', 'intl', 'gd', 'zip', 'fileinfo', 'bcmath', 'curl', 'exif'];

    /**
     * @return array<string, list<array{label: string, value: string, status?: string, hint?: string}>>
     */
    public function sections(): array
    {
        return [
            'Server' => $this->server(),
            'PHP' => $this->php(),
            'Application' => $this->application(),
            'Database' => $this->database(),
            'Cache, sessions & queue' => $this->background(),
            'Storage' => $this->storage(),
        ];
    }

    /**
     * The installed release ("v1.2.0 · built 2026-11-26 12:00"), from the
     * RELEASE.txt the release build writes, else the latest CHANGELOG entry.
     */
    public static function version(): string
    {
        $release = base_path('RELEASE.txt');

        if (is_file($release)) {
            $lines = file($release, FILE_IGNORE_NEW_LINES) ?: [];
            $version = trim(str_replace('SSO', '', $lines[0] ?? ''));
            $built = trim(str_replace('Built:', '', $lines[1] ?? ''));

            return $version.($built !== '' ? ' · built '.$built : '');
        }

        $changelog = base_path('CHANGELOG.md');

        if (is_file($changelog) && preg_match('/^## \[(\d+\.\d+\.\d+[^\]]*)\]/m', (string) file_get_contents($changelog), $m)) {
            return 'v'.$m[1].' · development copy';
        }

        return 'development copy';
    }

    private function server(): array
    {
        $cpus = getenv('NUMBER_OF_PROCESSORS') ?: (is_readable('/proc/cpuinfo') ? substr_count((string) file_get_contents('/proc/cpuinfo'), 'processor') : null);

        return array_values(array_filter([
            $this->row('Operating system', php_uname('s').' '.php_uname('r').' ('.php_uname('m').')'),
            $this->row('Server name', (string) gethostname()),
            $this->row('Web server', (string) (request()->server('SERVER_SOFTWARE') ?: 'Command line')),
            $cpus ? $this->row('Processors', (string) $cpus) : null,
            $this->row('Server time', now()->format('j M Y, H:i:s').' ('.config('app.timezone').')'),
        ]));
    }

    private function php(): array
    {
        $loaded = array_map('strtolower', get_loaded_extensions());
        $missing = array_values(array_diff(self::REQUIRED_EXTENSIONS, $loaded));
        $extensions = collect(self::REQUIRED_EXTENSIONS)
            ->map(fn (string $ext) => $ext.(in_array($ext, $loaded, true) ? ' ✓' : ' ✗'))
            ->join(' · ');

        $redis = app(PerformanceSettings::class);
        $phpredis = in_array('redis', $loaded, true);

        return [
            $this->row('Version', PHP_VERSION.' ('.PHP_SAPI.')', PHP_VERSION_ID >= 80400 ? 'ok' : 'fail', 'The SSO needs PHP 8.4 or later.'),
            $this->row('Memory limit', (string) ini_get('memory_limit')),
            $this->row('Max execution time', ini_get('max_execution_time').' s'),
            $this->row('Upload / post size', ini_get('upload_max_filesize').' / '.ini_get('post_max_size')),
            $this->row('Extensions', $extensions, $missing === [] ? 'ok' : 'fail', 'Enable in php.ini: '.implode(', ', $missing).' (docs/deployment.md, 1.1).'),
            $this->row('Redis extension (phpredis)', $phpredis ? 'Installed' : 'Not installed (Predis is used)',
                $phpredis || ! $redis->redis_enabled ? 'ok' : 'warn', 'Optional: phpredis is faster than the built-in Predis client.'),
            ...$this->opcache(),
        ];
    }

    private function opcache(): array
    {
        $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        if (! is_array($status) || ! ($status['opcache_enabled'] ?? false)) {
            return [$this->row('OPcache', 'Off', 'fail', 'Turn on OPcache in php.ini: every page is about 4 times slower without it (docs/deployment.md, 1.1).')];
        }

        $memory = $status['memory_usage'];
        $used = $memory['used_memory'] + $memory['wasted_memory'];
        $total = $used + $memory['free_memory'];
        $stats = $status['opcache_statistics'];
        $usedShare = $total > 0 ? $used / $total : 0;

        return [
            $this->row('OPcache', 'On · '.number_format($stats['num_cached_scripts']).' scripts cached · '.number_format($stats['opcache_hit_rate'], 1).'% hit rate'),
            $this->row('OPcache memory', $this->bytes($used).' of '.$this->bytes($total).' used',
                $usedShare < 0.9 ? 'ok' : 'warn', 'Nearly full: raise opcache.memory_consumption.'),
            $this->row('OPcache file check', ini_get('opcache.validate_timestamps') ? 'On (changed files are picked up)' : 'Off (restart Apache after each update)'),
        ];
    }

    private function application(): array
    {
        $debug = (bool) config('app.debug');
        $production = app()->environment('production');
        $packages = collect(['filament/filament' => 'Filament', 'livewire/livewire' => 'Livewire', 'laravel/passport' => 'Passport'])
            ->map(fn (string $name, string $package) => $name.' '.$this->package($package))
            ->join(' · ');

        return [
            $this->row('SSO version', self::version()),
            $this->row('Laravel', app()->version()),
            $this->row('Packages', $packages),
            $this->row('Environment', app()->environment().' · debug '.($debug ? 'ON' : 'off'),
                ! $debug ? 'ok' : ($production ? 'fail' : 'warn'), 'Set APP_DEBUG=false (and APP_ENV=production) on the live server.'),
            $this->row('Address (issuer)', (string) config('app.url')),
            $this->row('Maintenance mode', app()->isDownForMaintenance() ? 'On' : 'Off', app()->isDownForMaintenance() ? 'warn' : 'ok', 'The site shows a maintenance page: `php artisan up` ends it.'),
            $this->row('Configuration cache', app()->configurationIsCached() ? 'Cached' : 'Not cached',
                app()->configurationIsCached() ? 'ok' : 'warn', 'Run `php artisan optimize` on the server after each update.'),
            $this->row('Route cache', app()->routesAreCached() ? 'Cached' : 'Not cached',
                app()->routesAreCached() ? 'ok' : 'warn', 'Run `php artisan optimize` on the server after each update.'),
            $this->row('Event cache', app()->eventsAreCached() ? 'Cached' : 'Not cached'),
        ];
    }

    private function database(): array
    {
        $connection = DB::connection();

        try {
            $pdo = $connection->getPdo();
        } catch (Throwable $e) {
            return [$this->row('Connection', 'Failed: '.$e->getMessage(), 'fail', 'Check DB_* in .env and that SQL Server is reachable.')];
        }

        $driver = $connection->getDriverName();
        $rows = [
            $this->row('Connection', 'Connected ('.$driver.')', 'ok'),
            $this->row('Server', $this->serverVersion($pdo, $driver)),
            $this->row('Database', $connection->getDatabaseName().(filled($host = config("database.connections.{$connection->getName()}.host")) ? ' on '.$host : '')),
        ];

        if ($driver === 'sqlsrv') {
            try {
                $tables = collect(DB::select('
                    SELECT t.name AS name,
                           SUM(CASE WHEN p.index_id IN (0, 1) THEN p.rows ELSE 0 END) AS row_count,
                           SUM(a.total_pages) * 8192 AS bytes
                    FROM sys.tables t
                    JOIN sys.partitions p ON p.object_id = t.object_id
                    JOIN sys.allocation_units a ON a.container_id = p.partition_id
                    GROUP BY t.name'));

                $rows[] = $this->row('Size', $this->bytes((int) $tables->sum('bytes')).' in '.$tables->count().' tables');
                $rows[] = $this->row('Largest tables', $tables->sortByDesc('row_count')->take(5)
                    ->map(fn (object $t) => $t->name.' '.number_format((int) $t->row_count))
                    ->join(' · '));
            } catch (Throwable) {
                // No permission to read the catalog: leave the size out.
            }
        }

        return $rows;
    }

    private function background(): array
    {
        $effective = RedisRuntime::$effective ?: [
            'cache' => config('cache.default'), 'session' => config('session.driver'), 'queue' => config('queue.default'),
        ];
        $queue = (string) config('queue.default');
        $fallback = RedisRuntime::fallbackActive();

        try {
            $waiting = $queue === 'sync' ? null : Queue::connection($queue)->size();
        } catch (Throwable) {
            $waiting = null;
        }

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $scheduler = Cache::get('heartbeat:scheduler');
        $worker = Cache::get(QueueHeartbeat::KEY);

        return [
            $this->row('Cache / sessions / queue', $effective['cache'].' / '.$effective['session'].' / '.$effective['queue'],
                $fallback ? 'warn' : 'ok', 'Redis is unreachable: running on the database until it is back.'),
            $this->row('Scheduler', $scheduler ? 'Last ran '.$this->ago($scheduler) : 'Never ran',
                $scheduler && $scheduler > now()->subMinutes(5)->getTimestamp() ? 'ok' : 'fail',
                'Not running: add the Windows scheduled task (deploy\\scheduled-task.cmd, docs/deployment.md 3.5).'),
            $this->row('Queue worker', $worker ? 'Last worked '.$this->ago($worker) : 'Never worked',
                $worker && $worker > now()->subMinutes(15)->getTimestamp() ? 'ok' : 'warn',
                'The scheduled task works the queue every minute; check that it runs.'),
            $this->row('Queued jobs', $queue === 'sync' ? '0 (jobs run immediately)' : ($waiting === null ? 'Unknown' : number_format($waiting).' waiting'),
                $waiting === null || $waiting < 1000 ? 'ok' : 'warn', 'Many jobs are waiting: the worker cannot keep up.'),
            $this->row('Failed jobs', number_format($failed), $failed === 0 ? 'ok' : 'warn',
                'See Maintenance commands → List failed jobs; retry after fixing the cause.'),
        ];
    }

    private function storage(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        $logs = collect(glob(storage_path('logs/*.log')) ?: [])->sum(fn (string $file) => (int) @filesize($file));

        return array_values(array_filter([
            $this->row('storage folder', is_writable(storage_path()) ? 'Writable' : 'Not writable',
                is_writable(storage_path()) ? 'ok' : 'fail', 'Give the Apache account write access to storage\\.'),
            $this->row('bootstrap\\cache folder', is_writable(base_path('bootstrap/cache')) ? 'Writable' : 'Not writable',
                is_writable(base_path('bootstrap/cache')) ? 'ok' : 'fail', 'Give the Apache account write access to bootstrap\\cache\\.'),
            $free !== false && $total ? $this->row('Disk space', $this->bytes((int) $free).' free of '.$this->bytes((int) $total),
                $free / $total > 0.1 ? 'ok' : 'warn', 'Less than 10% free: logs, backups and the database may fail to write.') : null,
            $this->row('Log files', $this->bytes($logs).' (kept 30 days)'),
            $this->row('Token signing key', is_file(storage_path('oauth-private.key')) ? 'Present' : 'Missing',
                is_file(storage_path('oauth-private.key')) ? 'ok' : 'fail', 'Run `php artisan passport:keys` once.'),
        ]));
    }

    private function serverVersion(PDO $pdo, string $driver): string
    {
        try {
            if ($driver === 'sqlsrv') {
                $row = DB::selectOne("SELECT CAST(SERVERPROPERTY('ProductVersion') AS varchar(50)) AS v, CAST(SERVERPROPERTY('Edition') AS varchar(100)) AS e");

                return 'SQL Server '.$row->v.' · '.$row->e;
            }

            return ucfirst($driver).' '.$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable) {
            return ucfirst($driver);
        }
    }

    private function package(string $name): string
    {
        try {
            return InstalledVersions::isInstalled($name) ? (string) InstalledVersions::getPrettyVersion($name) : '—';
        } catch (Throwable) {
            return '—';
        }
    }

    private function ago(int $timestamp): string
    {
        return now()->setTimestamp($timestamp)->diffForHumans();
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        for ($value = (float) $bytes; $value >= 1024 && $i < count($units) - 1; $i++) {
            $value /= 1024;
        }

        return ($i === 0 ? (string) (int) $value : number_format($value, 1)).' '.$units[$i];
    }

    /**
     * @return array{label: string, value: string, status?: string, hint?: string}
     */
    private function row(string $label, string $value, ?string $status = null, ?string $hint = null): array
    {
        return array_filter([
            'label' => $label,
            'value' => $value,
            'status' => $status,
            'hint' => $status !== null && $status !== 'ok' ? $hint : null,
        ], fn ($v) => $v !== null);
    }
}
