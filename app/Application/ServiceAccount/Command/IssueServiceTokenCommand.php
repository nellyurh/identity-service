<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount\Command;

final readonly class IssueServiceTokenCommand
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public string $requestId,
    ) {}
}
