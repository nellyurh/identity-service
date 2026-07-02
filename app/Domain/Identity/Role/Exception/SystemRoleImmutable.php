<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role\Exception;

use App\Domain\Shared\Exception\DomainException;

/** Raised on any attempt to rename or delete a built-in (system) role. */
final class SystemRoleImmutable extends DomainException
{
    public static function forName(string $name): self
    {
        $e = new self("System role '{$name}' cannot be modified or removed.");
        $e->detail = ['role' => $name];

        return $e;
    }

    public function errorCode(): string
    {
        return 'ROLE_002';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
