<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * A capability a service account may be granted, e.g. `wallet.credit`, `notification.send`.
 * Same lexical form as a permission name (resource.action, colon-separable).
 */
final readonly class Scope implements Stringable
{
    private const string PATTERN = '/^[a-z][a-z0-9_]*[.:][a-z][a-z0-9_*]*$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("Invalid scope: {$value}");
        }
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
