<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount\Command;

final readonly class RotateServiceAccountCredentialCommand
{
    public function __construct(
        public string $serviceAccountId,
        public string $actorId,
        public string $requestId,
    ) {}
}
