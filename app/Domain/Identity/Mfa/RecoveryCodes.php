<?php

namespace App\Domain\Identity\Mfa;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * Single-use backup codes for when the authenticator is unavailable.
 * Only keyed hashes are stored (and the column itself is encrypted).
 */
final class RecoveryCodes
{
    public const int COUNT = 8;

    /**
     * Replaces the user's codes and returns the new plain codes (shown once).
     *
     * @return array<int, string>
     */
    public function regenerate(User $user): array
    {
        $codes = collect(range(1, self::COUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map($this->hash(...), $codes),
        ])->save();

        return $codes;
    }

    public function remaining(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Uses up a code if it is valid.
     */
    public function consume(User $user, string $code): bool
    {
        $hash = $this->hash(Str::lower(trim($code)));
        $stored = $user->two_factor_recovery_codes ?? [];

        foreach ($stored as $index => $candidate) {
            if (hash_equals($candidate, $hash)) {
                unset($stored[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($stored)])->save();

                return true;
            }
        }

        return false;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
