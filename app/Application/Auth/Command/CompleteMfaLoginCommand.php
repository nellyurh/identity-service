<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

final readonly class CompleteMfaLoginCommand
{
    public function __construct(
        public string $challengeToken,
        public string $code,
        public string $requestId,
    ) {}
}
