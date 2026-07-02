<?php

declare(strict_types=1);

namespace App\Application\ApiKey\Command;

final readonly class CreateApiKeyCommand
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $name,
        public string $ownerType,
        public string $ownerId,
        public array $scopes,
        public ?string $expiresAt,
        public string $actorId,
        public string $requestId,
    ) {}
}
