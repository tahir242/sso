<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $organization_name;

    public ?string $tagline;

    /** Uploaded application logo on the private "local" disk; null = built-in mark. See Branding. */
    public ?string $logo_path;

    public ?string $support_email;

    public ?string $support_phone;

    public string $timezone;

    /** Show occasion greetings / quote of the day on the login screen. */
    public bool $login_greetings_enabled;

    /**
     * Days added before converting to Hijri (Umm al-Qura). Use -1 when local
     * moon sighting usually puts Pakistan one day behind Saudi Arabia.
     */
    public int $hijri_offset_days;

    public static function group(): string
    {
        return 'general';
    }
}
