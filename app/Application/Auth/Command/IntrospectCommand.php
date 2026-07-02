<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

final readonly class IntrospectCommand
{
    public function __construct(
        public string $token,
        public string $requestId,
    ) {}
}
