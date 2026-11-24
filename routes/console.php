<?php

use App\Support\Operations\QueueHeartbeat;
use App\Support\Redis\RedisRuntime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. On Windows, Task Scheduler runs `php artisan schedule:run`
| every minute (deploy\scheduled-task.cmd sets it up). That one task is all
| the server needs: the queue is worked from here too, so no worker service
| (and no NSSM) is required.
*/

Schedule::command('access:escalate')->hourly()->withoutOverlapping();
Schedule::command('access:reviews')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('helpdesk:sla')->hourly()->withoutOverlapping();
Schedule::command('sso:backup')->dailyAt('01:30')->withoutOverlapping();

// Heartbeats checked by `php artisan sso:check`: the scheduler runs, and a queue worker picks up jobs.
Schedule::call(fn () => Cache::forever('heartbeat:scheduler', now()->getTimestamp()))->everyMinute()->name('heartbeat:scheduler');
Schedule::job(new QueueHeartbeat)->everyFiveMinutes()->name('heartbeat:queue');

// Queue (emails, WhatsApp, push, back-channel logout), kept last: every minute
// a worker handles what is waiting and exits as soon as the queue is empty, so
// jobs wait at most about a minute. It runs in the foreground on purpose:
// Windows background processes (`start /b`) are fragile under Task Scheduler.
// A separate always-on worker, if one is ever added, does no harm.
// The queue not in use is drained as well: it holds jobs from before a switch
// in Settings → Cache, queue & Redis, or from a moment Redis was unreachable.
foreach (RedisRuntime::idleQueues() as $connection) {
    Schedule::command("queue:work {$connection} --stop-when-empty --max-time=20 --tries=3 --sleep=1")
        ->everyMinute()->withoutOverlapping(5)->name("queue:{$connection}");
}

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --sleep=1')
    ->everyMinute()->withoutOverlapping(5)->name('queue');
