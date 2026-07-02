<?php

declare(strict_types=1);

namespace App\Domain\Identity\PasswordReset\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a password reset is requested. Carries an opaque delivery_ref (not a secret) that the
 * notification service exchanges — authenticated — for the freshly-minted token. No email, no token,
 * no hash: honours "no plaintext tokens/secrets/PII in event payloads".
 */
final readonly class PasswordResetRequested implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $deliveryRef,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'PasswordResetRequested';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'delivery_ref' => $this->deliveryRef,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
