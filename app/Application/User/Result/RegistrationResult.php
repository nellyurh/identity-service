<?php

declare(strict_types=1);

namespace App\Application\User\Result;

final readonly class RegistrationResult
{
    public function __construct(public string $userId) {}
}
