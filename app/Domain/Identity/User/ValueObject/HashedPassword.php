<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\ValueObject;

use InvalidArgumentException;

/**
 * A password hash produced by the PasswordHasher port (Argon2id in production). The domain
 * only ever holds the hash — never the plaintext — so "password never stored raw" is an
 * invariant of the type system, not a convention. The aggregate cannot be constructed with
 * a raw password because there is no VO for one.
 */
final readonly class HashedPassword
{
    private function __construct(public string $value) {}

    public static function fromHash(string $hash): self
    {
        if (trim($hash) === '') {
            throw new InvalidArgumentException('Password hash cannot be empty.');
        }

        return new self($hash);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
