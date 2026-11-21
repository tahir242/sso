<?php

namespace App\Domain\Identity\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Windows-style "recent accounts" for the sign-in screen.
 *
 * The list lives only in this browser, in an encrypted, HTTP-only cookie
 * (Laravel's EncryptCookies), so it cannot be read by scripts or forged.
 * It holds display data only — never a password or token — and is
 * re-checked against the database before it is shown, so a disabled or
 * renamed account silently drops off the list.
 */
final class RecentAccounts
{
    public const string COOKIE = 'sso_recent_accounts';

    public const int MAX = 5;

    private const int MINUTES = 60 * 24 * 180; // 180 days

    /** Gradient pairs for the avatars (full class names so Tailwind keeps them). */
    private const array PALETTE = [
        'from-rose-500 to-red-700',
        'from-amber-500 to-orange-700',
        'from-emerald-500 to-teal-700',
        'from-sky-500 to-blue-700',
        'from-violet-500 to-purple-700',
        'from-fuchsia-500 to-pink-700',
        'from-cyan-500 to-sky-700',
        'from-lime-500 to-green-700',
    ];

    /**
     * Remembered accounts, most recent first, still able to sign in.
     *
     * @return list<array{username: string, name: string, initials: string, color: string}>
     */
    public static function all(?Request $request = null): array
    {
        $usernames = self::usernames($request ?? request());

        if ($usernames === []) {
            return [];
        }

        $users = User::query()->whereIn('username', $usernames)->get()->keyBy(fn (User $u) => Str::lower($u->username));

        $accounts = [];
        foreach ($usernames as $username) {
            $user = $users->get(Str::lower($username));
            if ($user && $user->status->canSignIn()) {
                $accounts[] = self::present($user);
            }
        }

        return $accounts;
    }

    /**
     * Puts the user at the top of this browser's list.
     */
    public static function remember(User $user): void
    {
        $usernames = array_values(array_filter(
            self::usernames(request()),
            fn (string $u) => Str::lower($u) !== Str::lower($user->username),
        ));

        array_unshift($usernames, $user->username);

        self::store(array_slice($usernames, 0, self::MAX));
    }

    public static function forget(string $username): void
    {
        self::store(array_values(array_filter(
            self::usernames(request()),
            fn (string $u) => Str::lower($u) !== Str::lower($username),
        )));
    }

    /**
     * @return array{username: string, name: string, initials: string, color: string}
     */
    public static function present(User $user): array
    {
        return [
            'username' => $user->username,
            'name' => $user->name ?: $user->username,
            'initials' => self::initials($user->name ?: $user->username),
            'color' => self::PALETTE[crc32(Str::lower($user->username)) % count(self::PALETTE)],
        ];
    }

    public static function initials(string $name): string
    {
        return Str::of($name)->replaceMatches('/[^\pL\s]+/u', ' ')->squish()->explode(' ')->filter()
            ->take(2)->map(fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    }

    /**
     * @return list<string>
     */
    private static function usernames(Request $request): array
    {
        // A queued cookie (set earlier in this request) wins over the incoming one.
        $queued = Cookie::queued(self::COOKIE);
        $raw = $queued ? $queued->getValue() : $request->cookie(self::COOKIE);

        $list = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($list)) {
            return [];
        }

        return array_slice(array_values(array_filter($list, fn ($u) => is_string($u) && $u !== '' && strlen($u) <= 100)), 0, self::MAX);
    }

    /**
     * @param  list<string>  $usernames
     */
    private static function store(array $usernames): void
    {
        if ($usernames === []) {
            Cookie::queue(Cookie::forget(self::COOKIE));

            return;
        }

        Cookie::queue(Cookie::make(
            self::COOKIE,
            json_encode($usernames),
            self::MINUTES,
            secure: request()->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
