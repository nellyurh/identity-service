<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * kebab-case service identifier (identity, billing, wallet, gateway, notification,
 * storage), matching the platform ServiceName contract (common.schema.json #/$defs/ServiceName).
 */
final readonly class ServiceName implements Stringable
{
    private const string PATTERN = '/^[a-z][a-z0-9-]*$/';

    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException("Invalid service name: {$value}");
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
