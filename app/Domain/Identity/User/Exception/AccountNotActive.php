<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Exception;

use App\Domain\Identity\User\UserStatus;
use App\Domain\Shared\Exception\DomainException;

/** Raised when an operation requires an active account but the user is disabled or deleted. */
final class AccountNotActive extends DomainException
{
    public static function because(UserStatus $status): self
    {
        $e = new self("Account is {$status->value} and cannot authenticate.");
        $e->detail = ['status' => $status->value];

        return $e;
    }

    public function errorCode(): string
    {
        return 'USER_002';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
