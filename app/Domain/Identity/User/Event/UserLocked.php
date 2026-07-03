<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when consecutive failed logins trip the brute-force threshold and the account is
 * temporarily locked. Carries when the lock expires; never the attempted credentials.
 */
final readonly class UserLocked implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $lockedUntil,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'UserLocked';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'locked_until' => $this->lockedUntil,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
