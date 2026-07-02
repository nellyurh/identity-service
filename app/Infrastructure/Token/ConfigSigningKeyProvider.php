<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

use App\Application\Port\SigningKeyProvider;
use RuntimeException;

/**
 * Config-backed signing keys (PEMs sourced from env / Secrets Manager, never committed). One
 * key is *current* and signs; zero or more *verify-only* public keys are also honoured so a key
 * being rotated out keeps verifying tokens it already signed until they expire. JWKS publishes the
 * whole non-retired set. Rotation is therefore zero-downtime: promote a new current key and move
 * the previous public key into the verify-only set; drop it once its tokens have all expired.
 *
 * A DB-backed signing_keys lifecycle (with automated promotion/retirement) can replace this later
 * without changing the port.
 */
final readonly class ConfigSigningKeyProvider implements SigningKeyProvider
{
    /**
     * @param  array<string,string>  $verifyOnlyPublicKeys  kid => public PEM (previous keys, not retired)
     */
    public function __construct(
        private string $privateKeyPem,
        private string $publicKeyPem,
        private string $kid,
        private array $verifyOnlyPublicKeys = [],
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
        if ($kid === $this->kid && $this->publicKeyPem !== '') {
            return $this->publicKeyPem;
        }

        $pem = $this->verifyOnlyPublicKeys[$kid] ?? '';

        return $pem !== '' ? $pem : null;
    }

    /** @return list<array<string,string>> */
    public function jwks(): array
    {
        $jwks = [];

        if ($this->publicKeyPem !== '') {
            $jwks[] = $this->jwk($this->kid, $this->publicKeyPem);
        }

        foreach ($this->verifyOnlyPublicKeys as $kid => $pem) {
            if ($kid === $this->kid || $pem === '') {
                continue;
            }
            $jwks[] = $this->jwk($kid, $pem);
        }

        return $jwks;
    }

    /** @return array<string,string> */
    private function jwk(string $kid, string $pem): array
    {
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException("Configured JWT public key for kid {$kid} is not a valid PEM.");
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException("Configured JWT public key for kid {$kid} is not RSA.");
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => Jwt::base64UrlEncode((string) $details['rsa']['n']),
            'e' => Jwt::base64UrlEncode((string) $details['rsa']['e']),
        ];
    }
}
