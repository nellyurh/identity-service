<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role\Exception;

use App\Domain\Shared\Exception\DomainException;

final class RoleNameTaken extends DomainException
{
    public static function withName(string $name): self
    {
        $e = new self("A role named {$name} already exists.");
        $e->detail = ['role' => $name];

        return $e;
    }

    public function errorCode(): string
    {
        return 'ROLE_003';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
