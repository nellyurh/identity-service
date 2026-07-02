<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Port\PasswordHasher;

/**
 * Deterministic PasswordHasher double: the "hash" is a fixed prefix plus the plaintext, so
 * verify() is exact and tests are fully deterministic. needsRehash is togglable.
 */
final class FakePasswordHasher implements PasswordHasher
{
    private const string PREFIX = 'argon2id$';

    public function __construct(public bool $needsRehash = false) {}

    public function hash(string $plainText): string
    {
        return self::PREFIX.$plainText;
    }

    public function verify(string $plainText, string $hash): bool
    {
        return hash_equals($hash, self::PREFIX.$plainText);
    }

    public function needsRehash(string $hash): bool
    {
        return $this->needsRehash;
    }
}
