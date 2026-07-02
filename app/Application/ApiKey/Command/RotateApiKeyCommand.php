<?php

declare(strict_types=1);

namespace App\Application\ApiKey\Command;

final readonly class RotateApiKeyCommand
{
    public function __construct(
        public string $apiKeyId,
        public string $actorId,
        public string $requestId,
    ) {}
}
