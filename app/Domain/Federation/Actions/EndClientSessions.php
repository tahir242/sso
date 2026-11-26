<?php

namespace App\Domain\Federation\Actions;

use App\Domain\Federation\Jobs\SendBackChannelLogout;
use App\Domain\Federation\Models\ClientSession;
use App\Domain\Settings\SessionSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;

/**
 * Marks application sessions as ended and notifies each application through
 * back-channel logout.
 */
final class EndClientSessions
{
    private const int BATCH = 1000;

    public function __construct(
        private readonly SessionManager $sessions,
        private readonly SessionSettings $settings,
    ) {}

    public function forSid(?string $sid): int
    {
        return $sid ? $this->end(ClientSession::query()->where('sid', $sid)) : 0;
    }

    public function forUser(int $userId): int
    {
        return $this->end(ClientSession::query()->where('user_id', $userId));
    }

    /**
     * Application sessions whose SSO session has ended without a sign-out:
     * idle timeout, absolute timeout, or the browser session was removed
     * ("sign out other sessions", password change). Sessions recorded before
     * the browser session was tracked count as ended.
     */
    public function forExpiredSsoSessions(): int
    {
        $startedBefore = now()->subHours($this->settings->absolute_timeout_hours);
        $ended = 0;

        // In batches, so memory and query size stay the same however many
        // people are signed in.
        ClientSession::query()
            ->whereNull('logged_out_at')
            ->select(['id', 'sid', 'session_id', 'auth_time'])
            ->chunkById(self::BATCH, function (Collection $rows) use ($startedBefore, &$ended): void {
                $live = $this->liveSessionIds($rows->pluck('session_id')->filter()->unique()->values()->all());

                $rows->groupBy('sid')
                    ->reject(fn (Collection $sidRows) => $sidRows->contains(fn (ClientSession $row) => $row->session_id !== null
                        && $row->auth_time->greaterThanOrEqualTo($startedBefore)
                        && isset($live[$row->session_id])))
                    ->keys()
                    ->each(function (string $sid) use (&$ended): void {
                        $ended += $this->forSid($sid);
                    });
            });

        return $ended;
    }

    /**
     * Which of these browser sessions still exist and are not idle-expired.
     * One query for the database session store; other stores are asked one
     * by one (each a fast key lookup).
     *
     * @param  list<string>  $ids
     * @return array<string, true>
     */
    private function liveSessionIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $handler = $this->sessions->driver()->getHandler();

        if ($handler instanceof DatabaseSessionHandler) {
            return DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->whereIn('id', $ids)
                ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
                ->pluck('id')
                ->mapWithKeys(fn (string $id) => [$id => true])
                ->all();
        }

        return collect($ids)
            ->filter(fn (string $id) => (string) $handler->read($id) !== '')
            ->mapWithKeys(fn (string $id) => [$id => true])
            ->all();
    }

    private function end(Builder $query): int
    {
        $sessions = $query->whereNull('logged_out_at')->get();

        foreach ($sessions as $session) {
            $session->forceFill(['logged_out_at' => now()])->save();
            SendBackChannelLogout::dispatch($session->getKey());
        }

        return $sessions->count();
    }
}
