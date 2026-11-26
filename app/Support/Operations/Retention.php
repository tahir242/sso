<?php

namespace App\Support\Operations;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deletes records the SSO no longer needs, so tables stay small however many
 * people use it. Runs daily (`sso:prune`). Deletes in batches so no table is
 * locked for long. OAuth tokens are purged by `passport:purge`.
 */
final class Retention
{
    /** Application sessions, counted from sign-out. */
    public const int CLIENT_SESSION_DAYS = 30;

    /** Sign-in history (Audit → Sign-in events). */
    public const int AUTH_EVENT_DAYS = 365;

    /** Change history (who changed what in the admin console). */
    public const int ACTIVITY_LOG_DAYS = 365;

    /** Notifications the user has already read. */
    public const int READ_NOTIFICATION_DAYS = 90;

    public const int FAILED_JOB_DAYS = 30;

    private const int BATCH = 5000;

    /**
     * @return array<string, int> rows deleted per table
     */
    public function prune(): array
    {
        return [
            'client_sessions' => $this->deleteInBatches(DB::table('client_sessions')
                ->where('logged_out_at', '<', now()->subDays(self::CLIENT_SESSION_DAYS))),
            'auth_events' => $this->deleteInBatches(DB::table('auth_events')
                ->where('created_at', '<', now()->subDays(self::AUTH_EVENT_DAYS))),
            'activity_log' => $this->deleteInBatches(DB::table('activity_log')
                ->where('created_at', '<', now()->subDays(self::ACTIVITY_LOG_DAYS))),
            'notifications' => $this->deleteInBatches(DB::table('notifications')
                ->where('read_at', '<', now()->subDays(self::READ_NOTIFICATION_DAYS))),
            'trusted_devices' => $this->deleteInBatches(DB::table('trusted_devices')
                ->where('expires_at', '<', now())),
            'failed_jobs' => $this->deleteInBatches(DB::table('failed_jobs')
                ->where('failed_at', '<', now()->subDays(self::FAILED_JOB_DAYS))),
        ];
    }

    private function deleteInBatches(Builder $query): int
    {
        $total = 0;

        do {
            $deleted = (clone $query)->limit(self::BATCH)->delete();
            $total += $deleted;
        } while ($deleted === self::BATCH);

        return $total;
    }
}
