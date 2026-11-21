<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    /** smtp | log (log = development, nothing is sent). */
    public string $mailer;

    public ?string $host;

    public int $port;

    /** null (automatic) | smtp (plain/STARTTLS) | smtps (implicit TLS) */
    public ?string $scheme;

    public ?string $username;

    public ?string $password;

    public string $from_address;

    public string $from_name;

    public static function group(): string
    {
        return 'mail';
    }

    public static function encrypted(): array
    {
        return ['password'];
    }
}
