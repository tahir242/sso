<?php

namespace App\Domain\Federation\Oidc;

/**
 * The OIDC issuer identifier and the ACR values we advertise.
 */
final class Issuer
{
    /** Authenticated with a password only. */
    public const string ACR_PASSWORD = 'urn:sso:acr:pwd';

    /** Authenticated with a password and a second factor. */
    public const string ACR_MFA = 'urn:sso:acr:mfa';

    public static function url(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
