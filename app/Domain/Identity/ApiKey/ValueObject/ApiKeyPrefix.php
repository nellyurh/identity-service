<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * The public, indexed portion of an API key (the part before the dot in
 * `unero_<env>_<prefix>.<secret>`). Stored in cleartext for O(1) lookup and so a leaked key can be
 * scanned/identified by prefix without exposing the secret.
 */
final readonly class ApiKeyPrefix implements Stringable
{
    private const string PATTERN = '/^[a-z0-9]{6,32}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("Invalid API key prefix: {$value}");
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
