<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * A validated, normalized username: 3-32 chars, lowercase letters/digits and . _ - .
 * Normalized to lowercase so the uniqueness invariant is case-insensitive.
 */
final readonly class Username implements Stringable
{
    private const string PATTERN = '/^[a-z0-9][a-z0-9._-]{2,31}$/';

    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException("Invalid username: {$value}");
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
