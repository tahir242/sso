<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * Employee initials ("Jane Ann Doe" -> JAD,
 * "Muhammad Khalid Jamil" -> KJ): the first letter of each name word,
 * leaving out common prefixes, kept unique because applications such as
 * HR look people up by their initials.
 */
final class EmployeeInitials
{
    /** Name words that are not used for initials (unless nothing else is left). */
    private const array PREFIXES = ['muhammad', 'mohammad', 'mohammed', 'muhammed', 'mohd', 'md', 'syed', 'sayed', 'syeda', 'dr', 'mr', 'mrs', 'ms', 'miss', 'prof'];

    public const int MAX = 10;

    /**
     * Unique initials for this name (ignoring the given user, when editing).
     */
    public static function generate(string $name, ?int $ignoreUserId = null): ?string
    {
        $words = self::words($name);

        if ($words === []) {
            return null;
        }

        $base = implode('', array_map(fn (string $w) => mb_substr($w, 0, 1), array_slice($words, 0, 4)));
        $candidates = [$base];

        // Clashes: add the next letters of the last name word (KJ -> KJA), then numbers (KJ2).
        $last = end($words);
        for ($i = 1; $i < min(mb_strlen($last), 3); $i++) {
            $candidates[] = $base.mb_substr($last, $i, 1);
        }
        for ($n = 2; $n <= 99; $n++) {
            $candidates[] = $base.$n;
        }

        foreach ($candidates as $candidate) {
            $candidate = mb_substr(mb_strtoupper($candidate), 0, self::MAX);
            if (! self::taken($candidate, $ignoreUserId)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function taken(string $initials, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->whereRaw('UPPER(emp_initial) = ?', [mb_strtoupper(trim($initials))])
            ->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))
            ->exists();
    }

    /**
     * @return list<string>
     */
    private static function words(string $name): array
    {
        $all = array_values(array_filter(preg_split('/\s+/', trim((string) Str::of($name)->ascii()->replaceMatches('/[^A-Za-z\s]+/', ' '))) ?: []));
        $significant = array_values(array_filter($all, fn (string $w) => ! in_array(mb_strtolower($w), self::PREFIXES, true)));

        return $significant ?: $all;
    }
}
