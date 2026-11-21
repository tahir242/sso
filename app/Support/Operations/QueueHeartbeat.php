<?php

namespace App\Support\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Queued every few minutes by the scheduler. When a worker runs it, the
 * timestamp proves the queue is being processed (checked by sso:check).
 */
class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const KEY = 'heartbeat:queue';

    public function handle(): void
    {
        Cache::forever(self::KEY, now()->getTimestamp());
    }
}
