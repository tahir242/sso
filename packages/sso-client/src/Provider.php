<?php

namespace SsoClient;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SsoClient\Http\Transport;
use stdClass;
use Throwable;

/**
 * Talks to the SSO: discovery, signing keys and the token endpoint.
 * Discovery and keys are cached on disk for an hour.
 */
final class Provider
{
    private const CACHE_TTL = 3600;

    /** @var array<string, mixed>|null */
    private ?array $metadata = null;

    public function __construct(
        private readonly Config $config,
        private readonly Transport $http,
    ) {}

    public function endpoint(string $name): string
    {
        $metadata = $this->metadata();

        if (empty($metadata[$name]) || ! is_string($metadata[$name])) {
            throw new SsoException("The SSO does not publish {$name}.");
        }

        return $metadata[$name];
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    public function token(array $params): array
    {
        $response = $this->http->request('POST', $this->endpoint('token_endpoint'), [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $params + ['client_id' => $this->config->clientId, 'client_secret' => $this->config->clientSecret]);

        $data = json_decode($response['body'], true);

        if ($response['status'] !== 200 || ! is_array($data)) {
            $reason = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'unknown error') : 'invalid response';

            throw new SsoException("The SSO rejected the token request: {$reason}", $response['status']);
        }

        return $data;
    }

    /**
     * Verifies a JWT signed by the SSO (signature, issuer, audience, expiry).
     */
    public function verify(string $jwt): stdClass
    {
        JWT::$leeway = 60;

        try {
            $claims = JWT::decode($jwt, $this->keys());
        } catch (Throwable $first) {
            // Unknown key id after a key rotation: refresh the key set once.
            try {
                $claims = JWT::decode($jwt, $this->keys(refresh: true));
            } catch (Throwable $e) {
                throw new SsoException('Invalid token from SSO: '.$e->getMessage(), 0, $e);
            }
        }

        if (($claims->iss ?? null) !== $this->config->issuerUrl()) {
            throw new SsoException('Token was not issued by the configured SSO.');
        }

        if (! in_array($this->config->clientId, (array) ($claims->aud ?? []), true)) {
            throw new SsoException('Token was not issued for this application.');
        }

        return $claims;
    }

    /**
     * @return array<string, Key>
     */
    private function keys(bool $refresh = false): array
    {
        $jwks = $this->cached('jwks', fn () => $this->getJson($this->endpoint('jwks_uri')), $refresh);

        return JWK::parseKeySet($jwks, 'RS256');
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        return $this->metadata ??= $this->cached('discovery', function () {
            $metadata = $this->getJson($this->config->issuerUrl().'/.well-known/openid-configuration');

            if (($metadata['issuer'] ?? null) !== $this->config->issuerUrl()) {
                throw new SsoException('SSO discovery issuer does not match the configured issuer.');
            }

            return $metadata;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $response = $this->http->request('GET', $url);
        $data = json_decode($response['body'], true);

        if ($response['status'] !== 200 || ! is_array($data)) {
            throw new SsoException("Could not load {$url} (HTTP {$response['status']}).");
        }

        return $data;
    }

    /**
     * @param  callable(): array<string, mixed>  $load
     * @return array<string, mixed>
     */
    private function cached(string $name, callable $load, bool $refresh = false): array
    {
        $file = $this->config->cacheDir().DIRECTORY_SEPARATOR.$name.'.json';

        if (! $refresh && is_file($file) && filemtime($file) > time() - self::CACHE_TTL) {
            $data = json_decode((string) file_get_contents($file), true);

            if (is_array($data)) {
                return $data;
            }
        }

        $data = $load();
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return $data;
    }
}
