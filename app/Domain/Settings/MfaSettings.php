<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Two-step verification options. Whether a user has it is their own choice;
 * anyone who has turned it on is challenged at sign-in (unless the device is
 * trusted).
 */
class MfaSettings extends Settings
{
    /** "Remember this device" lifetime; 0 disables trusted devices. */
    public int $trusted_device_days;

    /** Issuer shown in authenticator apps. */
    public string $issuer;

    public static function group(): string
    {
        return 'mfa';
    }
}
