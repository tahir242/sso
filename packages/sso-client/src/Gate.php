<?php

namespace SsoClient;

use SsoClient\Http\Transport;
use Throwable;

/**
 * The quickest way to put a PHP application behind SSO: one config
 * file and one line at the top of every page (or of the file every page
 * already includes).
 *
 *     $user = \SsoClient\Gate::protect(__DIR__ . '/sso.php');
 *     echo $user->name;
 *
 * The page that calls protect() also answers the SSO's callbacks, so no
 * extra files are needed. Register these at the SSO (Applications →
 * Connect shows them filled in), where PAGE is that page's full URL:
 *
 *     Redirect URI             PAGE?sso=callback
 *     Back-channel logout URI  PAGE?sso=backchannel-logout
 *
 * Sign-out link anywhere in the app: Gate::logoutUrl()
 *
 * sso.php returns an array:
 *     issuer, client_id, client_secret, redirect_uri   required
 *     post_logout_redirect_uri                          page to land on after signing out
 *     cache_dir                                         writable folder, not web-readable
 *     roles                                             only users with one of these roles get in
 *     verify_tls                                        default true
 */
final class Gate
{
    private static ?Client $client = null;

    /** @var array<string, mixed> */
    private static array $settings = [];

    /**
     * Returns the signed-in user, sending anyone else to the SSO first.
     * Also answers ?sso=callback, ?sso=logout and ?sso=backchannel-logout.
     *
     * @param  string|array<string, mixed>  $config  Path to sso.php, or the settings array
     */
    public static function protect(string|array $config): Identity
    {
        $result = self::handle($config, $_GET, $_POST, $_SERVER);

        if ($result instanceof Identity) {
            return $result;
        }

        http_response_code($result->status);
        foreach ($result->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $result->body;
        exit;
    }

    /**
     * protect() without sending anything: returns the user, or the response
     * the browser (or the SSO) should get instead.
     *
     * @param  string|array<string, mixed>  $config
     * @param  array<string, mixed>  $get
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $server
     */
    public static function handle(string|array $config, array $get, array $post, array $server, ?Transport $http = null): Identity|GateResponse
    {
        $client = self::boot($config, $http);
        $home = self::home();

        switch ($get['sso'] ?? null) {
            case 'callback':
                try {
                    $result = $client->handleCallback($get);
                } catch (Throwable $e) {
                    error_log('SSO sign-in failed: '.$e->getMessage());

                    return GateResponse::page(400, 'Sign-in could not be completed', $e->getMessage(), $home);
                }

                return GateResponse::redirect($result['return_to'] ?? $home);

            case 'logout':
                $url = $client->logoutUrl();

                // Signing out ends the application's whole session, not just the SSO part.
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }

                return GateResponse::redirect($url);

            case 'backchannel-logout':
                if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                    return new GateResponse(405, ['Allow' => 'POST']);
                }

                try {
                    $client->handleBackChannelLogout((string) ($post['logout_token'] ?? ''));
                } catch (Throwable $e) {
                    error_log('SSO back-channel logout rejected: '.$e->getMessage());

                    return new GateResponse(400, ['Cache-Control' => 'no-store']);
                }

                return new GateResponse(200, ['Cache-Control' => 'no-store']);
        }

        $user = $client->identity();

        if (! $user) {
            return GateResponse::redirect($client->authorizationUrl(self::currentPath($server)));
        }

        $required = (array) (self::$settings['roles'] ?? []);
        if ($required !== [] && array_intersect($required, $user->roles) === []) {
            return GateResponse::page(403, 'No access', 'Your account does not have a role that can use this application. Ask its owner, or request access in the SSO portal.');
        }

        return $user;
    }

    /**
     * The signed-in user, or null, without redirecting (for public pages).
     *
     * @param  string|array<string, mixed>|null  $config  Needed only if protect() was not called first
     */
    public static function user(string|array|null $config = null): ?Identity
    {
        return self::boot($config ?? self::$settings)->identity();
    }

    /**
     * Link for a "Sign out" button: ends the session here and at the SSO.
     */
    public static function logoutUrl(): string
    {
        return self::home().'?sso=logout';
    }

    /**
     * The underlying client, e.g. Gate::client()->api()->users().
     */
    public static function client(): Client
    {
        return self::$client ?? throw new SsoException('Call Gate::protect() first.');
    }

    /**
     * Forgets the loaded configuration (tests, or long-running workers).
     */
    public static function reset(): void
    {
        [self::$client, self::$settings] = [null, []];
    }

    /**
     * @param  string|array<string, mixed>  $config
     */
    private static function boot(string|array $config, ?Transport $http = null): Client
    {
        if (self::$client && $http === null) {
            return self::$client;
        }

        $settings = is_string($config) ? require $config : $config;

        if (! is_array($settings)) {
            throw new SsoException('The SSO config file must return an array.');
        }

        if (session_status() === PHP_SESSION_NONE && ! headers_sent()) {
            session_start();
        }

        self::$settings = $settings;

        return self::$client = new Client(new Config(
            issuer: (string) ($settings['issuer'] ?? ''),
            clientId: (string) ($settings['client_id'] ?? ''),
            clientSecret: (string) ($settings['client_secret'] ?? ''),
            redirectUri: (string) ($settings['redirect_uri'] ?? ''),
            postLogoutRedirectUri: $settings['post_logout_redirect_uri'] ?? null,
            cacheDir: $settings['cache_dir'] ?? null,
            // 'scopes' replaces the defaults; 'extra_scopes' (e.g. ['phone', 'address']) adds to them.
            scopes: array_values(array_unique([
                ...(array) ($settings['scopes'] ?? Config::DEFAULT_SCOPES),
                ...(array) ($settings['extra_scopes'] ?? []),
            ])),
            verifyTls: (bool) ($settings['verify_tls'] ?? true),
        ), $http);
    }

    /** The page that runs the gate (the redirect URI without its query). */
    private static function home(): string
    {
        return (string) strtok((string) (self::$settings['redirect_uri'] ?? ''), '?');
    }

    /**
     * @param  array<string, mixed>  $server
     */
    private static function currentPath(array $server): string
    {
        $parts = parse_url((string) ($server['REQUEST_URI'] ?? '/'));
        parse_str($parts['query'] ?? '', $query);
        unset($query['sso']);

        return ($parts['path'] ?? '/').($query ? '?'.http_build_query($query) : '');
    }
}
