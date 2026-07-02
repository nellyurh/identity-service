<?php

declare(strict_types=1);

namespace App\Domain\Identity\Permission\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * A permission identifier in `resource.action` form, e.g. user.create, wallet.credit,
 * billing.refund. Immutable; the resource and action parts are individually addressable.
 */
final readonly class PermissionName implements Stringable
{
    private const string PATTERN = '/^([a-z][a-z0-9_]*)\.([a-z][a-z0-9_]*)$/';

    public string $value;

    public string $resource;

    public string $action;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (preg_match(self::PATTERN, $normalized, $m) !== 1) {
            throw new InvalidArgumentException("Invalid permission name: {$value}");
        }
        $this->value = $normalized;
        $this->resource = $m[1];
        $this->action = $m[2];
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
