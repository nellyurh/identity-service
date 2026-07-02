<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\ValueObject;

use InvalidArgumentException;

/**
 * The hash of an API key's secret portion. The plaintext secret is shown once at creation and never
 * stored — only this hash lives in the aggregate. Comparison is constant-time.
 */
final readonly class HashedApiKeySecret
{
    private function __construct(public string $value) {}

    public static function fromHash(string $hash): self
    {
        if (trim($hash) === '') {
            throw new InvalidArgumentException('API key secret hash cannot be empty.');
        }

        return new self($hash);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
