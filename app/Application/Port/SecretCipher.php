<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Authenticated symmetric encryption for secrets that must be recoverable at rest (e.g. TOTP
 * secrets, which the server has to decrypt to verify codes). The adapter owns the key material.
 */
interface SecretCipher
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $ciphertext): string;
}
