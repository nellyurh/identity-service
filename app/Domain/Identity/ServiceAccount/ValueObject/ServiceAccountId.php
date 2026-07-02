<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\ValueObject;

use InvalidArgumentException;
use Stringable;

final readonly class ServiceAccountId implements Stringable
{
    private const string PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("Invalid ServiceAccountId (expected ULID): {$value}");
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
