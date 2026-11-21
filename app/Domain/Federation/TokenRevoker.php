<?php

namespace App\Domain\Federation;

use Laravel\Passport\Passport;

/**
 * Revokes access and refresh tokens when access is taken away, so an
 * application cannot keep refreshing tokens for a disabled user or a
 * removed assignment.
 */
final class TokenRevoker
{
    public function forUser(int $userId, ?string $clientId = null): int
    {
        $tokens = Passport::token()->newQuery()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId));

        $ids = $tokens->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        foreach ($ids->chunk(500) as $chunk) {
            Passport::token()->newQuery()->whereIn('id', $chunk)->update(['revoked' => true]);
            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $chunk)->update(['revoked' => true]);
        }

        return $ids->count();
    }
}
