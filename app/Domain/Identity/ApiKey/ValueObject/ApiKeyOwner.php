<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\ValueObject;

use App\Domain\Identity\ApiKey\OwnerType;
use InvalidArgumentException;

/** The owner of an API key: a type (user | service_account) and the owner's ULID. */
final readonly class ApiKeyOwner
{
    private const string ULID = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    public function __construct(
        public OwnerType $type,
        public string $id,
    ) {
        if (preg_match(self::ULID, $id) !== 1) {
            throw new InvalidArgumentException("Invalid API key owner id (expected ULID): {$id}");
        }
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
