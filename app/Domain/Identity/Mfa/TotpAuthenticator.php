<?php

namespace App\Domain\Identity\Mfa;

use App\Domain\Identity\Models\User;
use App\Domain\Settings\MfaSettings;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time passwords (RFC 6238) for authenticator apps such as
 * Microsoft/Google Authenticator. Codes are single-use: a code's time slice
 * is remembered so it cannot be replayed.
 */
final readonly class TotpAuthenticator
{
    public function __construct(
        private Google2FA $engine,
        private MfaSettings $settings,
    ) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function otpauthUri(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl($this->settings->issuer, $user->username, $secret);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($this->otpauthUri($user, $secret));
    }

    /**
     * Verifies a 6-digit code (±1 time step for clock drift) and blocks reuse.
     */
    public function verify(User $user, string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen((string) $code) !== 6) {
            return false;
        }

        $cacheKey = "mfa:totp-last:{$user->getKey()}";
        // Passing an old timestamp (0 when none) makes the engine return the
        // matched time slice, which we remember to reject that code again.
        $timestamp = $this->engine->verifyKeyNewer($secret, $code, (int) Cache::get($cacheKey, 0), 1);

        if ($timestamp === false) {
            return false;
        }

        Cache::put($cacheKey, (int) $timestamp, now()->addMinutes(5));

        return true;
    }
}
