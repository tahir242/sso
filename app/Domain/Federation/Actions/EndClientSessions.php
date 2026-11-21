<?php

namespace App\Domain\Federation\Actions;

use App\Domain\Federation\Jobs\SendBackChannelLogout;
use App\Domain\Federation\Models\ClientSession;
use Illuminate\Database\Eloquent\Builder;

/**
 * Marks application sessions as ended and notifies each application through
 * back-channel logout.
 */
final class EndClientSessions
{
    public function forSid(?string $sid): int
    {
        return $sid ? $this->end(ClientSession::query()->where('sid', $sid)) : 0;
    }

    public function forUser(int $userId): int
    {
        return $this->end(ClientSession::query()->where('user_id', $userId));
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
