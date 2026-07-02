<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

/**
 * The principal performing an action (a human user or a service account). Carried on every
 * state change and every audit row. Mirrors config-service's Actor; shared across all
 * identity aggregates.
 */
final readonly class Actor
{
    public function __construct(
        public string $id,
        public string $type,
    ) {
        if ($id === '' || ! in_array($type, ['user', 'service'], true)) {
            throw new InvalidArgumentException('Invalid actor.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id && $this->type === $other->type;
    }
}
