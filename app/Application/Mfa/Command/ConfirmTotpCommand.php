<?php

declare(strict_types=1);

namespace App\Application\Mfa\Command;

final readonly class ConfirmTotpCommand
{
    public function __construct(
        public string $userId,
        public string $code,
        public string $actorId,
        public string $requestId,
    ) {}
}
