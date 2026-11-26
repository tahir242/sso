<?php

namespace App\Support\Operations;

use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Console\PurgeCommand;
use Spatie\LaravelSettings\Console\ClearCachedSettingsCommand;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * The fixed list of artisan commands super-admins may run from the admin
 * console (Settings → Maintenance commands). Nothing outside this list can be
 * run: the page only ever sends a key, never a command line.
 *
 * Deliberately absent: migrate / db:* (run them from the deployment script,
 * not a web request that can time out half-way), down/up (could lock the
 * console out), activitylog:clean (admin changes and access grants are kept
 * forever), queue:clear (would silently drop pending emails and logouts).
 */
final class MaintenanceCommands
{
    public const LOG = 'maintenance';

    /**
     * @return array<string, array{group: string, label: string, command: string, parameters: array<string, mixed>, description: string, confirm?: string, danger?: bool}>
     */
    public static function all(): array
    {
        return [
            // After a deployment
            'optimize' => [
                'group' => 'After a deployment',
                'label' => 'Optimize',
                'command' => 'optimize',
                'parameters' => [],
                'description' => 'Caches configuration, routes, views and events, plus Filament components and icons. Run after every deployment on the live server.',
            ],
            'optimize-clear' => [
                'group' => 'After a deployment',
                'label' => 'Clear optimization caches',
                'command' => 'optimize:clear',
                'parameters' => [],
                'description' => 'Removes the cached configuration, routes, views, events and Filament files, and clears the application cache. Use when a change does not show up.',
                'confirm' => 'Also clears the application cache: rate limits and sign-in lockouts are reset. The first requests afterwards are slower.',
            ],
            'config-cache' => ['group' => 'After a deployment', 'label' => 'Cache configuration', 'command' => 'config:cache', 'parameters' => [], 'description' => 'Combines all config files into one. Needed again after editing .env or config/*.php.'],
            'config-clear' => ['group' => 'After a deployment', 'label' => 'Clear configuration cache', 'command' => 'config:clear', 'parameters' => [], 'description' => 'Reads .env and config/*.php again on every request.'],
            'route-cache' => ['group' => 'After a deployment', 'label' => 'Cache routes', 'command' => 'route:cache', 'parameters' => [], 'description' => 'Faster routing on the live server.'],
            'route-clear' => ['group' => 'After a deployment', 'label' => 'Clear route cache', 'command' => 'route:clear', 'parameters' => [], 'description' => 'Use after deploying new or changed routes without optimizing.'],
            'view-cache' => ['group' => 'After a deployment', 'label' => 'Compile views', 'command' => 'view:cache', 'parameters' => [], 'description' => 'Pre-compiles every Blade template.'],
            'view-clear' => ['group' => 'After a deployment', 'label' => 'Clear compiled views', 'command' => 'view:clear', 'parameters' => [], 'description' => 'Fixes pages that still show an old template.'],
            'event-cache' => ['group' => 'After a deployment', 'label' => 'Cache events', 'command' => 'event:cache', 'parameters' => [], 'description' => 'Caches the event → listener map.'],
            'event-clear' => ['group' => 'After a deployment', 'label' => 'Clear event cache', 'command' => 'event:clear', 'parameters' => [], 'description' => 'Use when a new listener does not run.'],
            'filament-optimize' => ['group' => 'After a deployment', 'label' => 'Cache admin console', 'command' => 'filament:optimize', 'parameters' => [], 'description' => 'Caches Filament components and Blade icons.'],
            'filament-optimize-clear' => ['group' => 'After a deployment', 'label' => 'Clear admin console cache', 'command' => 'filament:optimize-clear', 'parameters' => [], 'description' => 'Use when a new admin page or resource is missing from the menu.'],

            // Cache
            'cache-clear' => [
                'group' => 'Cache',
                'label' => 'Clear application cache',
                'command' => 'cache:clear',
                'parameters' => [],
                'description' => 'Empties the cache store in use (Redis or database). Sessions are not affected.',
                'confirm' => 'Rate limits, sign-in lockouts and the scheduler/queue heartbeats are reset. Nobody is signed out.',
            ],
            'settings-clear' => ['group' => 'Cache', 'label' => 'Reload settings', 'command' => 'settings:clear-cache', 'class' => ClearCachedSettingsCommand::class, 'parameters' => [], 'description' => 'Forgets cached settings. Needed after a deployment that adds new settings.'],
            'permission-reset' => ['group' => 'Cache', 'label' => 'Reload roles & permissions', 'command' => 'permission:cache-reset', 'parameters' => [], 'description' => 'Use when a role change in the database does not take effect.'],
            'schedule-clear' => ['group' => 'Cache', 'label' => 'Release stuck scheduled tasks', 'command' => 'schedule:clear-cache', 'parameters' => [], 'description' => 'Removes “still running” locks left by a task that crashed, so it runs again.'],

            // Queue
            'queue-work' => [
                'group' => 'Queue',
                'label' => 'Process queued jobs now',
                'command' => 'queue:work',
                'parameters' => ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3, '--sleep' => 1],
                'description' => 'Sends waiting emails, WhatsApp messages, push notifications and logout notices now (up to 50 seconds). The scheduler normally does this every minute.',
            ],
            'queue-restart' => ['group' => 'Queue', 'label' => 'Restart queue workers', 'command' => 'queue:restart', 'parameters' => [], 'description' => 'Workers finish their current job and restart with the latest code and settings.'],
            'queue-failed' => ['group' => 'Queue', 'label' => 'List failed jobs', 'command' => 'queue:failed', 'parameters' => [], 'description' => 'Jobs that failed after all retries.'],
            'queue-retry' => [
                'group' => 'Queue',
                'label' => 'Retry all failed jobs',
                'command' => 'queue:retry',
                'parameters' => ['id' => ['all']],
                'description' => 'Puts every failed job back on the queue.',
                'confirm' => 'Every failed job runs again: users may receive an email or WhatsApp message twice if it partly went through before.',
            ],
            'queue-prune-failed' => ['group' => 'Queue', 'label' => 'Delete failed jobs older than 7 days', 'command' => 'queue:prune-failed', 'parameters' => ['--hours' => 168], 'description' => 'Housekeeping for the failed_jobs table.'],
            'queue-flush' => [
                'group' => 'Queue',
                'label' => 'Delete all failed jobs',
                'command' => 'queue:flush',
                'parameters' => [],
                'description' => 'Empties the failed_jobs table.',
                'confirm' => 'Failed jobs are deleted permanently and cannot be retried.',
                'danger' => true,
            ],

            // Housekeeping
            'passport-purge' => ['group' => 'Housekeeping', 'label' => 'Purge expired tokens', 'command' => 'passport:purge', 'class' => PurgeCommand::class, 'parameters' => [], 'description' => 'Deletes revoked and expired access tokens, refresh tokens and authorization codes.'],
            'sso-prune' => ['group' => 'Housekeeping', 'label' => 'Prune old records', 'command' => 'sso:prune', 'parameters' => [], 'description' => 'Deletes application sessions 30 days after sign-out, sign-in events and change history older than a year, read notifications older than 90 days, expired remembered computers and failed jobs older than 30 days. Runs nightly at 01:00 anyway.'],
            'auth-clear-resets' => ['group' => 'Housekeeping', 'label' => 'Delete expired password-reset links', 'command' => 'auth:clear-resets', 'parameters' => [], 'description' => 'Removes expired reset tokens.'],
            'backup' => [
                'group' => 'Housekeeping',
                'label' => 'Back up now',
                'command' => 'sso:backup',
                'parameters' => [],
                'description' => 'Encrypted backup of keys, .env and private files, plus a SQL Server copy-only backup. Runs nightly at 01:30 anyway.',
                'confirm' => 'Can take a few minutes and adds load on the database server.',
            ],

            // Diagnostics
            'check' => ['group' => 'Diagnostics', 'label' => 'Readiness check', 'command' => 'sso:check', 'parameters' => [], 'description' => 'Configuration, keys, scheduler, queue, Redis and integrations.'],
            'about' => ['group' => 'Diagnostics', 'label' => 'About this installation', 'command' => 'about', 'parameters' => [], 'description' => 'Versions, environment, drivers and caches.'],
            'migrate-status' => ['group' => 'Diagnostics', 'label' => 'Database migrations', 'command' => 'migrate:status', 'parameters' => [], 'description' => 'Shows which migrations ran; pending ones need a deployment.'],
            'schedule-list' => ['group' => 'Diagnostics', 'label' => 'Scheduled tasks', 'command' => 'schedule:list', 'parameters' => [], 'description' => 'What the scheduler runs and when it runs next.'],
        ];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::all() as $key => $definition) {
            $groups[$definition['group']][$key] = $definition;
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(?string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Runs one predefined command and records it in the activity log.
     *
     * @return array{key: string, label: string, command: string, exit_code: int, output: string, duration_ms: int, at: string}
     */
    public static function run(string $key, ?User $user): array
    {
        $definition = self::find($key) ?? throw new \InvalidArgumentException("Unknown maintenance command [{$key}].");

        $line = trim($definition['command'].' '.self::describeParameters($definition['parameters']));
        $lock = Cache::lock('maintenance-command', 600);

        if (! $lock->get()) {
            return self::result($key, $definition, $line, 1, 'Another maintenance command is still running. Try again when it has finished.', 0);
        }

        @set_time_limit(600);
        ignore_user_abort(true);

        $output = new BufferedOutput;
        $started = hrtime(true);

        try {
            $kernel = app(Kernel::class);

            // Some packages register their commands only when PHP runs from the
            // command line; register those explicitly for a web request.
            if (isset($definition['class']) && ! array_key_exists($definition['command'], $kernel->all())) {
                $kernel->registerCommand(app($definition['class']));
            }

            $exit = $kernel->call($definition['command'], $definition['parameters'], $output);
            $text = $output->fetch();
        } catch (Throwable $e) {
            $exit = 1;
            $text = $output->fetch().PHP_EOL.get_class($e).': '.$e->getMessage();
        } finally {
            rescue(fn () => $lock->release(), report: false);
        }

        $ms = (int) ((hrtime(true) - $started) / 1e6);
        $text = trim(preg_replace('/\e\[[\d;]*m/', '', (string) $text)) ?: '(no output)';

        activity(self::LOG)
            ->causedBy($user)
            ->event('command')
            ->withProperties(['key' => $key, 'command' => $line, 'exit_code' => $exit, 'duration_ms' => $ms, 'output' => mb_substr($text, 0, 4000)])
            ->log('ran '.$line);

        return self::result($key, $definition, $line, $exit, $text, $ms);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function describeParameters(array $parameters): string
    {
        return collect($parameters)->map(function ($value, $name) {
            if (! str_starts_with($name, '--')) {
                return implode(' ', (array) $value);
            }

            return $value === true ? $name : "{$name}={$value}";
        })->implode(' ');
    }

    private static function result(string $key, array $definition, string $line, int $exit, string $output, int $ms): array
    {
        return [
            'key' => $key,
            'label' => $definition['label'],
            'command' => $line,
            'exit_code' => $exit,
            'output' => $output,
            'duration_ms' => $ms,
            'at' => now()->toDateTimeString(),
        ];
    }
}
