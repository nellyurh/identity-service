<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Exception;

use App\Domain\Shared\Exception\DomainException;

final class EmailAlreadyVerified extends DomainException
{
    public static function create(): self
    {
        return new self('Email is already verified.');
    }

    public function errorCode(): string
    {
        return 'USER_003';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
