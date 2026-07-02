<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a user's password hash changes (change or reset). Never carries the hash. */
final readonly class PasswordChanged implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'PasswordChanged';
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
