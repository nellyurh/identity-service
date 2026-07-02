<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

final readonly class RefreshCommand
{
    public function __construct(
        public string $refreshToken,
        public string $requestId,
    ) {}
}
