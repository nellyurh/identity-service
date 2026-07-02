<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * A validated, normalized email address. Normalization (trim + lowercase) makes the
 * uniqueness invariant meaningful — the repository enforces uniqueness on the normalized
 * value.
 */
final readonly class Email implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }
        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
