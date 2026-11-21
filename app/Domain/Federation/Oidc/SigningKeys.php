<?php

namespace App\Domain\Federation\Oidc;

use Laravel\Passport\Passport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

/**
 * The RS256 key pair Passport already uses (storage/oauth-*.key, outside the
 * web root). ID tokens and logout tokens are signed with it; the public half
 * is published as a JWK.
 */
final class SigningKeys
{
    private ?Configuration $configuration = null;

    public function jwt(): Configuration
    {
        return $this->configuration ??= Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($this->privatePem()),
            InMemory::plainText($this->publicPem()),
        );
    }

    /**
     * Key id: stable fingerprint of the public key, so clients can pick the
     * right key after a rotation.
     */
    public function kid(): string
    {
        return substr(hash('sha256', $this->publicPem()), 0, 16);
    }

    /**
     * @return array<string, string>
     */
    public function jwk(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->publicPem()));

        if (! $details || ! isset($details['rsa'])) {
            throw new RuntimeException('The OAuth public key is not an RSA key.');
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->kid(),
            'n' => self::base64Url($details['rsa']['n']),
            'e' => self::base64Url($details['rsa']['e']),
        ];
    }

    private function privatePem(): string
    {
        return $this->read('private');
    }

    private function publicPem(): string
    {
        return $this->read('public');
    }

    private function read(string $type): string
    {
        $path = Passport::keyPath("oauth-{$type}.key");

        if (! is_file($path)) {
            throw new RuntimeException("Missing {$path}. Run `php artisan passport:keys`.");
        }

        return (string) file_get_contents($path);
    }

    private static function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
