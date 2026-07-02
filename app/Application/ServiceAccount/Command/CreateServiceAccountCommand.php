<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount\Command;

final readonly class CreateServiceAccountCommand
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $name,
        public array $scopes,
        public string $actorId,
        public string $requestId,
    ) {}
}
