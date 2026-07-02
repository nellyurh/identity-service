<?php

declare(strict_types=1);

namespace App\Application\PasswordReset\Command;

final readonly class MaterializePasswordResetDeliveryCommand
{
    public function __construct(
        public string $deliveryRef,
        public string $actorId,
        public string $requestId,
    ) {}
}
