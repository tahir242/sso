<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Risk-based MFA policy. Admin roles and off-network sign-ins require a second
 * factor; anyone who has enrolled is always challenged (unless the device is
 * trusted).
 */
class MfaSettings extends Settings
{
    public bool $required_for_admins;

    public bool $required_off_network;

    /** @var array<int, string> CIDR ranges treated as the hospital network. */
    public array $trusted_networks;

    /** "Remember this device" lifetime; 0 disables trusted devices. */
    public int $trusted_device_days;

    /** Issuer shown in authenticator apps. */
    public string $issuer;

    public static function group(): string
    {
        return 'mfa';
    }
}
