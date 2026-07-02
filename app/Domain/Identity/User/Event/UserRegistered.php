<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a user is registered. NOTE (contract reconciliation, see EVENTS.md): the
 * shared repository currently ships `UserCreated.schema.json` (fields: user_id, tier,
 * created_at). This event uses identity's own vocabulary; before it is published to the
 * bus (outbox milestone) the shared schema must be reconciled — either add
 * `UserRegistered.schema.json` or map this event onto `UserCreated`. Not resolved here.
 */
final readonly class UserRegistered implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $username,
        public bool $emailVerified,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'UserRegistered';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'username' => $this->username,
            'email_verified' => $this->emailVerified,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
