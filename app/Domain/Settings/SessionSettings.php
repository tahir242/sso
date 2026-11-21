<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

class SessionSettings extends Settings
{
    /** Sign the user out after this many minutes without activity. */
    public int $idle_timeout_minutes;

    /** Hard limit on an SSO session, regardless of activity. */
    public int $absolute_timeout_hours;

    /** Lifetime of access tokens (and ID tokens) issued to applications. */
    public int $access_token_minutes;

    /** Lifetime of refresh tokens; keep it at or below the session length. */
    public int $refresh_token_hours;

    public static function group(): string
    {
        return 'session';
    }
}
