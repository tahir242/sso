<?php

namespace App\Domain\Identity\Auth;

use App\Domain\Identity\Models\User;

/**
 * A user whose password is verified but who still has to pass (or set up)
 * MFA. Kept in the session for a few minutes; nothing is signed in yet.
 */
final class PendingLogin
{
    private const string KEY = 'auth.pending';

    private const int TTL_SECONDS = 600;

    public static function start(User $user): void
    {
        session()->regenerate();
        session()->put(self::KEY, ['user_id' => $user->getKey(), 'at' => now()->getTimestamp()]);
    }

    public static function user(): ?User
    {
        $pending = session(self::KEY);

        if (! is_array($pending) || now()->getTimestamp() - ($pending['at'] ?? 0) > self::TTL_SECONDS) {
            self::clear();

            return null;
        }

        $user = User::find($pending['user_id'] ?? null);

        return $user?->status->canSignIn() ? $user : null;
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}
