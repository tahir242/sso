<?php

namespace SsoClient;

use SsoClient\Http\CurlTransport;
use SsoClient\Http\Transport;

/**
 * Signs users in to a PHP application through single sign-on
 * (OpenID Connect authorization code flow with PKCE).
 *
 *   $sso = new Client(new Config(...));
 *   $identity = $sso->identity();            // null when not signed in
 *   if (! $identity) $sso->redirectToLogin(); // exits
 *
 * State lives in $_SESSION['sso_client']; start the PHP session first.
 */
final class Client
{
    private const SESSION_KEY = 'sso_client';

    private const PENDING_TTL = 600;

    private readonly Provider $provider;

    private readonly RevokedSessions $revoked;

    private readonly Transport $http;

    public function __construct(
        private readonly Config $config,
        ?Transport $http = null,
    ) {
        $this->http = $http ?? new CurlTransport($config->verifyTls, $config->timeout);
        $this->provider = new Provider($config, $this->http);
        $this->revoked = new RevokedSessions($config);
    }

    /**
     * The signed-in user, refreshing tokens when needed. Returns null when
     * there is no SSO session, it was ended elsewhere (back-channel logout),
     * or the SSO no longer allows it (refresh refused).
     */
    public function identity(): ?Identity
    {
        $state = $this->session()['auth'] ?? null;

        if (! is_array($state)) {
            return null;
        }

        if ($this->revoked->isRevoked($state['claims']['sid'] ?? null)) {
            $this->forget();

            return null;
        }

        if (($state['expires_at'] ?? 0) <= time() + 30 && ! $this->refresh()) {
            return null;
        }

        return Identity::fromClaims($this->session()['auth']['claims']);
    }

    /**
     * URL that starts sign-in at the SSO.
     *
     * @param  string|null  $returnTo  Local path to return to after sign-in
     * @param  array<string, string>  $extra  e.g. ['prompt' => 'login']
     */
    public function authorizationUrl(?string $returnTo = null, array $extra = []): string
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        $pending = array_filter(
            $this->session()['pending'] ?? [],
            fn (array $p) => $p['created'] > time() - self::PENDING_TTL,
        );
        $pending[$state] = [
            'nonce' => $nonce,
            'verifier' => $verifier,
            'return_to' => $this->safeReturnPath($returnTo),
            'created' => time(),
        ];
        $this->put('pending', array_slice($pending, -5, null, true)); // a few tabs at once

        return $this->provider->endpoint('authorization_endpoint').'?'.http_build_query([
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->config->scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            ...$extra,
        ]);
    }

    public function redirectToLogin(?string $returnTo = null): never
    {
        header('Location: '.$this->authorizationUrl($returnTo), true, 302);
        exit;
    }

    /**
     * Completes sign-in on the redirect URI.
     *
     * @param  array<string, mixed>  $query  Usually $_GET
     * @return array{identity: Identity, return_to: ?string}
     */
    public function handleCallback(array $query): array
    {
        if (isset($query['error'])) {
            throw new SsoException('Sign-in was not completed: '.($query['error_description'] ?? $query['error']));
        }

        $stateKey = (string) ($query['state'] ?? '');
        $pending = $this->session()['pending'][$stateKey] ?? null;

        if (! $pending || $pending['created'] < time() - self::PENDING_TTL || empty($query['code'])) {
            throw new SsoException('Sign-in request expired or was not started here. Please try again.');
        }

        $all = $this->session()['pending'];
        unset($all[$stateKey]);
        $this->put('pending', $all);

        $tokens = $this->provider->token([
            'grant_type' => 'authorization_code',
            'code' => (string) $query['code'],
            'redirect_uri' => $this->config->redirectUri,
            'code_verifier' => $pending['verifier'],
        ]);

        $claims = $this->verifyIdToken($tokens, $pending['nonce']);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true); // prevent session fixation
        }

        $this->store($tokens, $claims);

        return ['identity' => Identity::fromClaims($claims), 'return_to' => $pending['return_to']];
    }

    /**
     * Ends the local session and returns the SSO logout URL (single logout).
     */
    public function logoutUrl(): string
    {
        $idToken = $this->session()['auth']['id_token'] ?? null;
        $this->forget();

        return $this->provider->endpoint('end_session_endpoint').'?'.http_build_query(array_filter([
            'id_token_hint' => $idToken,
            'client_id' => $this->config->clientId,
            'post_logout_redirect_uri' => $this->config->postLogoutRedirectUri,
        ]));
    }

    /**
     * Back-channel logout endpoint: verifies the SSO's logout token and marks
     * that SSO session as ended. Call from a POST-only script and answer
     * HTTP 200 on success, 400 when this throws.
     */
    public function handleBackChannelLogout(string $logoutToken): void
    {
        $claims = $this->provider->verify($logoutToken);
        $events = (array) ($claims->events ?? []);

        if (! array_key_exists('http://schemas.openid.net/event/backchannel-logout', $events) || isset($claims->nonce) || empty($claims->sid)) {
            throw new SsoException('Not a valid back-channel logout token.');
        }

        $this->revoked->revoke((string) $claims->sid);
    }

    /**
     * Forgets the SSO sign-in in this application only.
     */
    public function forget(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || isset($_SESSION)) {
            unset($_SESSION[self::SESSION_KEY]['auth']);
        }
    }

    /**
     * Server-to-server API client using this application's credentials.
     */
    public function api(): ApiClient
    {
        return new ApiClient($this->config, $this->provider, $this->http);
    }

    private function refresh(): bool
    {
        $auth = $this->session()['auth'];

        if (empty($auth['refresh_token'])) {
            $this->forget();

            return false;
        }

        try {
            $tokens = $this->provider->token(['grant_type' => 'refresh_token', 'refresh_token' => $auth['refresh_token']]);
            $claims = isset($tokens['id_token']) ? $this->verifyIdToken($tokens, null) : $auth['claims'];

            if (($claims['sub'] ?? null) !== ($auth['claims']['sub'] ?? null)) {
                throw new SsoException('Refreshed token belongs to another user.');
            }

            $this->store($tokens + ['refresh_token' => $auth['refresh_token']], $claims, $tokens['id_token'] ?? $auth['id_token']);

            return true;
        } catch (SsoException) {
            // Access removed, account disabled or session ended at the SSO.
            $this->forget();

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @return array<string, mixed>
     */
    private function verifyIdToken(array $tokens, ?string $nonce): array
    {
        if (empty($tokens['id_token'])) {
            throw new SsoException('The SSO did not return an ID token.');
        }

        $claims = json_decode((string) json_encode($this->provider->verify($tokens['id_token'])), true);

        if ($nonce !== null && ! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new SsoException('Sign-in response did not match this request (nonce).');
        }

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @param  array<string, mixed>  $claims
     */
    private function store(array $tokens, array $claims, ?string $idToken = null): void
    {
        $this->put('auth', [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => time() + (int) ($tokens['expires_in'] ?? 300),
            'id_token' => $idToken ?? $tokens['id_token'],
            'claims' => $claims,
        ]);
    }

    private function safeReturnPath(?string $returnTo): ?string
    {
        // Only local paths: never redirect to another site after sign-in.
        return is_string($returnTo) && str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//') ? $returnTo : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function session(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE && ! isset($_SESSION)) {
            throw new SsoException('Start the PHP session before using the SSO client.');
        }

        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    private function put(string $key, mixed $value): void
    {
        $this->session();
        $_SESSION[self::SESSION_KEY][$key] = $value;
    }
}
