<?php

namespace App\Support\Operations;

use App\Domain\Identity\Models\User;
use App\Domain\Settings\LdapSettings;
use App\Domain\Settings\MailSettings;
use App\Domain\Settings\PerformanceSettings;
use App\Support\Redis\RedisRuntime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Pest\TestSuite;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Go-live checks: configuration, database, keys, background processing,
 * integrations and (optionally) what the live site actually sends.
 * Each result is [status, area, message] with status pass | warn | fail.
 */
final class ReadinessCheck
{
    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $results = [];

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public function run(bool $http = false): array
    {
        $this->results = [];

        $this->application();
        $this->database();
        $this->signingKeys();
        $this->sessions();
        $this->background();
        $this->redis();
        $this->integrations();
        $this->administrators();

        if ($http) {
            $this->liveSite();
        }

        return $this->results;
    }

    private function application(): void
    {
        $production = app()->environment('production');
        $this->check($production, 'App', 'APP_ENV is production', 'APP_ENV is '.app()->environment().' (must be production on the live server)', warnOnly: true);
        $this->check(! config('app.debug'), 'App', 'Debug mode is off', 'APP_DEBUG is on: errors would show code and secrets to users', warnOnly: ! $production);

        $url = (string) config('app.url');
        $this->check(str_starts_with($url, 'https://'), 'App', "Issuer {$url} uses HTTPS", "Issuer {$url} is not HTTPS (tokens and passwords would cross the network in clear)", warnOnly: ! $production);

        $this->check(app()->configurationIsCached() && app()->routesAreCached(), 'App', 'Configuration and routes are cached', 'Run `php artisan optimize` after each deployment', warnOnly: true);
        $this->check(! class_exists(TestSuite::class), 'App', 'Development packages are not installed', 'Development packages are installed; deploy with `composer install --no-dev`', warnOnly: true);
    }

    private function database(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->fail('Database', 'Cannot connect: '.$e->getMessage());

            return;
        }

        $driver = DB::getDriverName();
        $this->check($driver === 'sqlsrv', 'Database', 'SQL Server', "Using {$driver}; production is meant to run on SQL Server", warnOnly: true);

        if ($driver === 'sqlsrv') {
            $version = (string) DB::selectOne("SELECT CAST(SERVERPROPERTY('ProductVersion') AS varchar(50)) AS v")->v;
            $this->check((int) $version >= 14, 'Database', "SQL Server {$version} (2017 or later)", "SQL Server {$version} is older than 2017, the oldest version Laravel supports", warnOnly: (int) $version >= 13);
        }

        $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()
            ->diff(Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : []);
        $this->check($pending->isEmpty(), 'Database', 'All migrations have run', $pending->count().' migration(s) pending: run `php artisan migrate --force`');
    }

    private function signingKeys(): void
    {
        $private = storage_path('oauth-private.key');

        if (! is_file($private)) {
            $this->fail('Keys', 'No token signing key: run `php artisan passport:keys` once (never on every deploy)');

            return;
        }

        $key = openssl_pkey_get_private((string) file_get_contents($private));
        $details = $key ? openssl_pkey_get_details($key) : [];
        $this->check(($details['bits'] ?? 0) >= 2048, 'Keys', 'Token signing key is RSA '.($details['bits'] ?? '?').' bits', 'Token signing key is shorter than 2048 bits');
        $this->pass('Keys', 'Back up storage/oauth-*.key: losing them signs every user out of every application');
    }

    private function sessions(): void
    {
        $https = str_starts_with((string) config('app.url'), 'https://');
        $this->check(! $https || config('session.secure'), 'Sessions', 'Session cookie is HTTPS-only', 'SESSION_SECURE_COOKIE must be true over HTTPS');
        $this->check(config('session.http_only'), 'Sessions', 'Session cookie is hidden from JavaScript', 'session.http_only is off');
        $this->check(in_array(config('session.driver'), ['database', 'redis'], true), 'Sessions', 'Sessions stored in '.config('session.driver'), 'Session driver '.config('session.driver').' loses sessions or does not scale; use database');
    }

    private function background(): void
    {
        $this->check(config('queue.default') !== 'sync', 'Background', 'Queue driver '.config('queue.default'), 'QUEUE_CONNECTION=sync: emails and back-channel logouts would slow every request', warnOnly: true);

        $scheduler = Cache::get('heartbeat:scheduler');
        $this->check($scheduler && $scheduler > now()->subMinutes(5)->getTimestamp(), 'Background', 'Scheduler ran in the last 5 minutes',
            'Scheduler not running: Task Scheduler must run `php artisan schedule:run` every minute (escalations, reviews, SLA, backups)');

        $queue = Cache::get(QueueHeartbeat::KEY);
        $this->check($queue && $queue > now()->subMinutes(15)->getTimestamp(), 'Background', 'Queue worker processed a job in the last 15 minutes',
            'Queue not worked: the scheduled task (deploy\\scheduled-task.cmd) runs it every minute; check that task (notifications, back-channel logout)');

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())->count();
            $this->check($failed === 0, 'Background', 'No failed jobs in the last 24 hours', "{$failed} failed job(s) in the last 24 hours: see `php artisan queue:failed`", warnOnly: true);
        }
    }

    private function redis(): void
    {
        $settings = app(PerformanceSettings::class);
        if (! $settings->usesRedis()) {
            $this->pass('Redis', 'Not used (cache '.config('cache.default').', queue '.config('queue.default').')');

            return;
        }

        $probe = RedisRuntime::probe($settings->toArray());
        $this->check($probe['ok'], 'Redis', 'Redis '.($probe['version'] ?? '').' reachable ('.($probe['latency_ms'] ?? '?').' ms, '.$probe['client'].')',
            'Redis unreachable: '.($probe['error'] ?? '').($settings->redis_fallback ? ' (running on the database meanwhile)' : ''));

        foreach ($probe['warnings'] ?? [] as $warning) {
            $this->warn('Redis', $warning);
        }
    }

    private function integrations(): void
    {
        $mail = app(MailSettings::class);
        $this->check($mail->mailer === 'smtp' && filled($mail->host), 'Integrations', "Email via SMTP ({$mail->host})", 'Email is not sent (mailer '.$mail->mailer.'): set SMTP under Settings → Email', warnOnly: true);

        $ldap = app(LdapSettings::class);
        if ($ldap->enabled) {
            $this->check($ldap->encryption !== 'none', 'Integrations', 'Active Directory over '.strtoupper($ldap->encryption), 'Active Directory without encryption: Windows passwords cross the network in clear');
            $this->check($ldap->verify_certificate, 'Integrations', 'AD certificate is verified', 'AD certificate is not verified', warnOnly: true);
        } else {
            $this->warn('Integrations', 'Active Directory sign-in is off');
        }
    }

    private function administrators(): void
    {
        $admins = Role::where('name', 'super-admin')->exists() ? User::role('super-admin')->get() : collect();

        if ($admins->isEmpty()) {
            $this->fail('Access', 'No super-admin: run `php artisan sso:create-admin`');

            return;
        }

        $this->check($admins->count() >= 2, 'Access', $admins->count().' super-admins', 'Only '.$admins->count().' super-admin: add a second so nobody is locked out', warnOnly: true);

        $withoutMfa = $admins->filter(fn (User $u) => ! $u->two_factor_confirmed_at)->pluck('username');
        $this->check($withoutMfa->isEmpty(), 'Access', 'Every super-admin has two-step verification', 'No two-step verification yet: '.$withoutMfa->join(', '), warnOnly: true);
    }

    private function liveSite(): void
    {
        try {
            $response = Http::timeout(10)->withoutVerifying()->get(rtrim((string) config('app.url'), '/').'/login');
        } catch (Throwable $e) {
            $this->fail('Live site', 'Cannot reach '.config('app.url').': '.$e->getMessage());

            return;
        }

        $this->check($response->header('X-Frame-Options') === 'DENY' && $response->header('Content-Security-Policy') !== '', 'Live site', 'Security headers are sent', 'Security headers missing on /login');
        $this->check($response->header('X-Powered-By') === '', 'Live site', 'PHP version is not advertised', 'X-Powered-By header reveals the PHP version: set expose_php = Off in php.ini', warnOnly: true);
        $this->check(! str_contains((string) $response->header('Server'), '/'), 'Live site', 'Web server version is not advertised', 'Server header reveals the Apache version: set ServerTokens Prod in httpd.conf', warnOnly: true);

        if (str_starts_with((string) config('app.url'), 'https://')) {
            $this->check($response->header('Strict-Transport-Security') !== '', 'Live site', 'HSTS is on', 'No Strict-Transport-Security header');
        }

        foreach (['/.env', '/storage/logs/laravel.log', '/../.env', '/composer.json'] as $path) {
            $status = Http::timeout(10)->withoutVerifying()->get(rtrim((string) config('app.url'), '/').$path)->status();
            $this->check($status >= 400, 'Live site', "{$path} is not served ({$status})", "{$path} is publicly readable (HTTP {$status})");
        }
    }

    private function check(bool $ok, string $area, string $pass, string $problem, bool $warnOnly = false): void
    {
        $ok ? $this->pass($area, $pass) : ($warnOnly ? $this->warn($area, $problem) : $this->fail($area, $problem));
    }

    private function pass(string $area, string $message): void
    {
        $this->results[] = ['pass', $area, $message];
    }

    private function warn(string $area, string $message): void
    {
        $this->results[] = ['warn', $area, $message];
    }

    private function fail(string $area, string $message): void
    {
        $this->results[] = ['fail', $area, $message];
    }
}
