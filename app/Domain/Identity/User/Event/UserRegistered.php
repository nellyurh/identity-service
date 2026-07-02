<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a user is registered. Business-intent name (not "UserCreated"). Carries no
 * PII — consumers that need email/username read them from the identity API; the event only
 * announces that a user now exists and whether their email is verified.
 * Matches unero-shared-schemas/schemas/events/UserRegistered.schema.json.
 */
final readonly class UserRegistered implements DomainEvent
{
    public function __construct(
        public string $userId,
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
            'email_verified' => $this->emailVerified,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
