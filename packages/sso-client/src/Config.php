<?php

namespace SsoClient;

/**
 * Connection settings for one application.
 *
 * Keep the client secret out of version control (e.g. in the application's
 * untracked config file).
 */
final class Config
{
    /** Scopes asked for by default: sign-in, name and photo, email, employee details, roles. */
    public const DEFAULT_SCOPES = ['openid', 'profile', 'email', 'employee', 'roles'];

    /** Further standard scopes an application may add: `phone` (phone_number) and `address`. */
    public const OPTIONAL_SCOPES = ['phone', 'address'];

    /**
     * @param  string  $issuer  SSO base URL, e.g. https://sso.example.com
     * @param  string  $redirectUri  Registered callback URL of this application
     * @param  string|null  $postLogoutRedirectUri  Registered page to return to after signing out
     * @param  string|null  $cacheDir  Writable folder outside public access (discovery, keys, revoked sessions, API token)
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly ?string $postLogoutRedirectUri = null,
        public readonly ?string $cacheDir = null,
        public readonly array $scopes = self::DEFAULT_SCOPES,
        public readonly bool $verifyTls = true,
        public readonly int $timeout = 10,
    ) {
        if ($issuer === '' || $clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new SsoException('SSO issuer, client ID, client secret and redirect URI are required.');
        }
    }

    public function issuerUrl(): string
    {
        return rtrim($this->issuer, '/');
    }

    public function cacheDir(): string
    {
        $dir = rtrim($this->cacheDir ?? sys_get_temp_dir().DIRECTORY_SEPARATOR.'sso-client-'.md5($this->clientId), '/\\');

        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new SsoException("Cannot create SSO cache directory {$dir}.");
            }

            // Belt and braces if the folder is inside a web root (Apache).
            @file_put_contents($dir.DIRECTORY_SEPARATOR.'.htaccess', "Require all denied\n");
        }

        return $dir;
    }
}
