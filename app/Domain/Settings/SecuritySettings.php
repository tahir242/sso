<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    public int $password_min_length;

    /** Failed attempts per account before a temporary lockout. */
    public int $lockout_max_attempts;

    public int $lockout_decay_minutes;

    /** Failed attempts per IP (across all accounts) before throttling. */
    public int $ip_max_attempts;

    public static function group(): string
    {
        return 'security';
    }
}
