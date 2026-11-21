<?php

namespace SsoClient\Tests;

use Firebase\JWT\JWT;
use SsoClient\Http\Transport;

/**
 * In-memory stand-in for the SSO server: discovery, JWKS, token endpoint.
 */
final class FakeSso implements Transport
{
    public const ISSUER = 'https://sso.example.test';

    public const CLIENT_ID = 'client-123';

    private static ?array $keyPair = null;

    /** @var array<int, array{method: string, url: string, form: ?array}> */
    public array $requests = [];

    /** @var callable(array<string, string>): array{status: int, body: string} */
    public $tokenHandler;

    /** @var callable(string, string, ?array): array{status: int, body: string} method, path, form */
    public $apiHandler;

    public function __construct()
    {
        $this->tokenHandler = fn (array $form) => ['status' => 500, 'body' => '{}'];
        $this->apiHandler = fn (string $method, string $path, ?array $form) => ['status' => 404, 'body' => '{}'];
    }

    public function request(string $method, string $url, array $headers = [], ?array $form = null): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'form' => $form];

        return match (true) {
            str_ends_with($url, '/.well-known/openid-configuration') => $this->json([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER.'/oauth/authorize',
                'token_endpoint' => self::ISSUER.'/oauth/token',
                'jwks_uri' => self::ISSUER.'/oauth/jwks',
                'end_session_endpoint' => self::ISSUER.'/oauth/logout',
            ]),
            str_ends_with($url, '/oauth/jwks') => $this->json(['keys' => [self::jwk()]]),
            str_ends_with($url, '/oauth/token') => ($this->tokenHandler)($form ?? []),
            str_starts_with($url, self::ISSUER.'/api/') => ($this->apiHandler)($method, substr($url, strlen(self::ISSUER)), $form),
            default => ['status' => 404, 'body' => '{}'],
        };
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function sign(array $claims): string
    {
        return JWT::encode([
            'iss' => self::ISSUER,
            'aud' => [self::CLIENT_ID],
            'iat' => time(),
            'exp' => time() + 900,
            ...$claims,
        ], self::keys()['private'], 'RS256', 'test-key');
    }

    /**
     * @return array{status: int, body: string}
     */
    public function json(array $data, int $status = 200): array
    {
        return ['status' => $status, 'body' => json_encode($data)];
    }

    /**
     * @return array{private: string, n: string, e: string}
     */
    private static function keys(): array
    {
        if (self::$keyPair === null) {
            $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
            // Windows PHP builds need an explicit openssl.cnf path.
            if (! getenv('OPENSSL_CONF') && is_file(PHP_BINDIR.'/extras/ssl/openssl.cnf')) {
                $config['config'] = PHP_BINDIR.'/extras/ssl/openssl.cnf';
            }
            $key = openssl_pkey_new($config);
            openssl_pkey_export($key, $private, null, $config);
            $details = openssl_pkey_get_details($key);
            self::$keyPair = ['private' => $private, 'n' => $details['rsa']['n'], 'e' => $details['rsa']['e']];
        }

        return self::$keyPair;
    }

    private static function jwk(): array
    {
        $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-key', 'n' => $b64(self::keys()['n']), 'e' => $b64(self::keys()['e'])];
    }
}
