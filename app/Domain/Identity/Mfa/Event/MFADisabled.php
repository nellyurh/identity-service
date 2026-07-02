<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a user turns off a second factor. Carries the method; never any secret. */
final readonly class MFADisabled implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $method,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'MFADisabled';
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
