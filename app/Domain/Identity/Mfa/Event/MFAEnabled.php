<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a user activates a second factor. Carries the method (e.g. totp), never the secret. */
final readonly class MFAEnabled implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $method,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'MFAEnabled';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'method' => $this->method,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
