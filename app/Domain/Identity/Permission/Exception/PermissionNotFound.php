<?php

declare(strict_types=1);

namespace App\Domain\Identity\Permission\Exception;

use App\Domain\Shared\Exception\DomainException;

final class PermissionNotFound extends DomainException
{
    public static function withName(string $name): self
    {
        $e = new self("No permission named {$name}.");
        $e->detail = ['permission' => $name];

        return $e;
    }

    public function errorCode(): string
    {
        return 'PERMISSION_001';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
