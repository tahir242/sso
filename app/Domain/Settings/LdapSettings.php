<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Active Directory connection, edited in the admin console.
 * Bind password is stored encrypted.
 */
class LdapSettings extends Settings
{
    public bool $enabled;

    /** @var array<int, string> Domain controllers, tried in order. */
    public array $hosts;

    public int $port;

    /** ssl = LDAPS (636, recommended), tls = StartTLS (389), none = plain (dev only). */
    public string $encryption;

    public string $base_dn;

    /** Service account used to look users up (e.g. svc-sso@corp.local). */
    public ?string $bind_username;

    public ?string $bind_password;

    public int $timeout;

    /**
     * Validate the domain controller's TLS certificate. Turn off only while
     * the internal CA is being installed on the web server.
     */
    public bool $verify_certificate;

    /** Create an SSO account automatically the first time an AD user signs in. */
    public bool $auto_create_users;

    /** Optional extra LDAP filter users must match, e.g. (memberOf=CN=Staff,OU=Groups,DC=corp,DC=local). */
    public ?string $user_filter;

    public static function group(): string
    {
        return 'ldap';
    }

    public static function encrypted(): array
    {
        return ['bind_password'];
    }

    public function isConfigured(): bool
    {
        return $this->enabled && $this->hosts !== [] && $this->base_dn !== '';
    }
}
