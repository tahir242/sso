<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Auth\PendingLogin;
use App\Domain\Identity\Auth\RecentAccounts;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Starts the SSO session once every required factor has been verified.
 */
final class CompleteLogin
{
    public const string STARTED_AT = 'auth.started_at';

    public const string MFA_AT = 'auth.mfa_at';

    /** Random SSO session id, sent to applications as the OIDC `sid` claim. */
    public const string SID = 'auth.sid';

    private const string DEVICE_COOKIE = 'sso_device';

    /**
     * @return string URL to send the user to
     */
    public function handle(User $user, bool $mfaVerified): string
    {
        PendingLogin::clear();

        Auth::login($user);
        session()->regenerate();
        session()->put(self::STARTED_AT, now()->getTimestamp());
        session()->put(self::MFA_AT, $mfaVerified ? now()->getTimestamp() : null);
        session()->put(self::SID, Str::random(40));

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        RecentAccounts::remember($user);

        [$deviceHash, $newDevice] = $this->deviceInfo($user);
        AuthEvent::record(AuthEventType::LoginSucceeded, $user, meta: ['mfa' => $mfaVerified, 'new_device' => $newDevice], deviceHash: $deviceHash);

        $panel = filament()->getDefaultPanel();
        $isAdmin = $user->canAccessPanel($panel);
        $default = self::homeFor($user);

        // The page remembered before sign-in may belong to someone else on
        // this browser (e.g. an administrator signed out of /admin). Never
        // send a user without console access into the console: it would
        // only show "Forbidden".
        if (! $isAdmin && self::isConsoleUrl((string) session()->get('url.intended'), $panel->getPath())) {
            session()->forget('url.intended');
        }

        // Every account needs an email address (password recovery, alerts):
        // ask for it once, then continue to where the user was going.
        if (blank($user->email)) {
            session()->put('url.intended', session()->get('url.intended', $default));

            return route('account.email');
        }

        return session()->pull('url.intended', $default);
    }

    /**
     * Where a signed-in user starts: the admin console when their roles
     * allow it, otherwise the staff portal (My applications).
     */
    public static function homeFor(User $user): string
    {
        $panel = filament()->getDefaultPanel();

        return $user->canAccessPanel($panel) ? url($panel->getPath()) : route('home');
    }

    private static function isConsoleUrl(string $url, string $panelPath): bool
    {
        if ($url === '') {
            return false;
        }

        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $panel = '/'.trim($panelPath, '/');

        return $path === $panel || str_starts_with($path, $panel.'/');
    }

    /**
     * Recognises the browser with a long-lived random cookie (only its hash is
     * logged). A sign-in from an unrecognised browser — after the user's very
     * first sign-in — triggers a "new sign-in" alert.
     *
     * @return array{0: string, 1: bool} device hash, whether it is a new browser for this user
     */
    private function deviceInfo(User $user): array
    {
        $token = request()->cookie(self::DEVICE_COOKIE);

        if (! is_string($token) || strlen($token) < 32) {
            $token = Str::random(48);
            Cookie::queue(Cookie::make(self::DEVICE_COOKIE, $token, 60 * 24 * 730, secure: request()->isSecure(), httpOnly: true, sameSite: 'lax'));
        }

        $hash = hash('sha256', $token);
        $previous = AuthEvent::query()->where('user_id', $user->getKey())->where('event', AuthEventType::LoginSucceeded);

        return [$hash, $previous->clone()->exists() && ! $previous->clone()->where('device_hash', $hash)->exists()];
    }
}
