<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Hashing boundary. The domain holds only HashedPassword / HashedSecret and never learns
 * which algorithm produced them; the Argon2id adapter lives in Infrastructure. Application
 * services hash plaintext here, then hand the domain a hashed value object.
 */
interface PasswordHasher
{
    public function hash(string $plainText): string;

    public function verify(string $plainText, string $hash): bool;

    public function needsRehash(string $hash): bool;
}
