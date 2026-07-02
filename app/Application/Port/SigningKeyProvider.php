<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Supplies the signing material for access tokens. The private key never leaves this service;
 * public keys are published via jwks() so every other service can verify tokens offline.
 * Multi-key rotation (verify against several keys, sign with the current one) lands with the
 * signing_keys lifecycle in a later slice; today there is one active key.
 */
interface SigningKeyProvider
{
    /** kid of the key currently used to sign. */
    public function currentKid(): string;

    /** PEM of the private key for the current kid (issuer only). */
    public function privateKeyPem(): string;

    /** PEM of the public key for a given kid, or null if unknown/retired. */
    public function publicKeyPemForKid(string $kid): ?string;

    /**
     * Public verification keys in JWKS form.
     *
     * @return list<array<string,string>>
     */
    public function jwks(): array;
}
