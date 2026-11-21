<?php

namespace App\Domain\Identity\Mfa;

use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Identity\Models\TrustedDevice;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\MfaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * "Remember this device": skips the MFA challenge on a browser the user has
 * already verified. The cookie holds a random token; the database keeps only
 * its hash, so a leaked database cannot be used to forge the cookie.
 */
final readonly class TrustedDeviceManager
{
    public const string COOKIE = 'sso_trusted_device';

    public function __construct(private MfaSettings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->trusted_device_days > 0;
    }

    public function isTrusted(User $user, Request $request): bool
    {
        $token = $request->cookie(self::COOKIE);

        if (! $this->enabled() || ! is_string($token) || $token === '') {
            return false;
        }

        $device = $user->trustedDevices()->valid()->where('token_hash', hash('sha256', $token))->first();

        $device?->forceFill(['last_used_at' => now(), 'ip_address' => $request->ip()])->save();

        return $device !== null;
    }

    public function trust(User $user, Request $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $token = Str::random(64);
        $days = $this->settings->trusted_device_days;

        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'label' => self::describe((string) $request->userAgent()),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        Cookie::queue(Cookie::make(self::COOKIE, $token, $days * 24 * 60, secure: $request->isSecure(), httpOnly: true, sameSite: 'lax'));

        AuthEvent::record(AuthEventType::TrustedDeviceAdded, $user);
    }

    public function revoke(TrustedDevice $device): void
    {
        $device->delete();

        AuthEvent::record(AuthEventType::TrustedDeviceRevoked, $device->user, meta: ['device' => $device->label]);
    }

    public function revokeAll(User $user): void
    {
        $user->trustedDevices()->delete();
    }

    /**
     * Short human label such as "Chrome on Windows".
     */
    public static function describe(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$platform}";
    }
}
