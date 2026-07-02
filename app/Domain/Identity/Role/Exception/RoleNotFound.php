<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role\Exception;

use App\Domain\Shared\Exception\DomainException;

final class RoleNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        $e = new self("No role with id {$id}.");
        $e->detail = ['role_id' => $id];

        return $e;
    }

    public function errorCode(): string
    {
        return 'ROLE_001';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
