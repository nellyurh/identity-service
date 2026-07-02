<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

use App\Application\Port\SigningKeyProvider;
use RuntimeException;

/**
 * Single-active-key provider backed by config (PEMs sourced from env / Secrets Manager, never
 * committed). Multi-key rotation via the signing_keys table replaces this in a later slice
 * without changing the port.
 */
final readonly class ConfigSigningKeyProvider implements SigningKeyProvider
{
    public function __construct(
        private string $privateKeyPem,
        private string $publicKeyPem,
        private string $kid,
    ) {}

    public function currentKid(): string
    {
        return $this->kid;
    }

    public function privateKeyPem(): string
    {
        return $this->privateKeyPem;
    }

    public function publicKeyPemForKid(string $kid): ?string
    {
        return $kid === $this->kid && $this->publicKeyPem !== '' ? $this->publicKeyPem : null;
    }

    /** @return list<array<string,string>> */
    public function jwks(): array
    {
        if ($this->publicKeyPem === '') {
            return [];
        }

        $key = openssl_pkey_get_public($this->publicKeyPem);
        if ($key === false) {
            throw new RuntimeException('Configured JWT public key is not a valid PEM.');
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Configured JWT public key is not RSA.');
        }

        return [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->kid,
            'n' => Jwt::base64UrlEncode((string) $details['rsa']['n']),
            'e' => Jwt::base64UrlEncode((string) $details['rsa']['e']),
        ]];
    }
}
