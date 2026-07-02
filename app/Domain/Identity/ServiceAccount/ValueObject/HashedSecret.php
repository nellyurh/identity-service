<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\ValueObject;

use InvalidArgumentException;

/**
 * The hash of a service account's client secret. Like a user password, the plaintext secret
 * is shown once at creation and never stored — only this hash lives in the aggregate.
 */
final readonly class HashedSecret
{
    private function __construct(public string $value) {}

    public static function fromHash(string $hash): self
    {
        if (trim($hash) === '') {
            throw new InvalidArgumentException('Secret hash cannot be empty.');
        }

        return new self($hash);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
