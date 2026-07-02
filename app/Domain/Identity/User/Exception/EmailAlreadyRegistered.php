<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Exception;

use App\Domain\Shared\Exception\DomainException;

final class EmailAlreadyRegistered extends DomainException
{
    public static function withEmail(string $email): self
    {
        $e = new self('Email is already registered.');
        $e->detail = ['email' => $email];

        return $e;
    }

    public function errorCode(): string
    {
        return 'USER_004';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
