<?php

declare(strict_types=1);

namespace App\Domain\Identity\Permission\Exception;

use App\Domain\Shared\Exception\DomainException;

final class PermissionAlreadyExists extends DomainException
{
    public static function withName(string $name): self
    {
        $e = new self("A permission named {$name} already exists.");
        $e->detail = ['permission' => $name];

        return $e;
    }

    public function errorCode(): string
    {
        return 'PERMISSION_002';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
