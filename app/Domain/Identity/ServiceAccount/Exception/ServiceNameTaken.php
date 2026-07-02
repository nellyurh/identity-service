<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ServiceNameTaken extends DomainException
{
    public static function withName(string $name): self
    {
        $e = new self("A service account named {$name} already exists.");
        $e->detail = ['name' => $name];

        return $e;
    }

    public function errorCode(): string
    {
        return 'SERVICE_003';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
