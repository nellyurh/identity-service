<?php

declare(strict_types=1);

namespace App\Application\Mfa\Command;

final readonly class DisableMfaCommand
{
    public function __construct(
        public string $userId,
        public string $actorId,
        public string $requestId,
    ) {}
}
