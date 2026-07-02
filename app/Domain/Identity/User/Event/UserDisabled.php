<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a user is disabled (cannot authenticate until re-enabled). */
final readonly class UserDisabled implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'UserDisabled';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
