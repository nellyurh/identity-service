<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

final readonly class LogoutResult
{
    public function __construct(
        public string $userId,
        public string $familyId,
    ) {}
}
