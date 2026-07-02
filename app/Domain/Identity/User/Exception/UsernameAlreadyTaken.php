<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Exception;

use App\Domain\Shared\Exception\DomainException;

final class UsernameAlreadyTaken extends DomainException
{
    public static function withUsername(string $username): self
    {
        $e = new self('Username is already taken.');
        $e->detail = ['username' => $username];

        return $e;
    }

    public function errorCode(): string
    {
        return 'USER_005';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
