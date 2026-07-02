<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role\ValueObject;

use InvalidArgumentException;
use Stringable;

/** snake_case role identifier, e.g. super_admin, organization_owner, member, service. */
final readonly class RoleName implements Stringable
{
    private const string PATTERN = '/^[a-z][a-z0-9_]*$/';

    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException("Invalid role name: {$value}");
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
