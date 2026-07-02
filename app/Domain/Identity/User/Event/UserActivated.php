<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a user's account becomes active (email verified, or re-enabled). */
final readonly class UserActivated implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $reason,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'UserActivated';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
