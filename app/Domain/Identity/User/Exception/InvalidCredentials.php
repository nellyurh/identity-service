<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Exception;

use App\Domain\Shared\Exception\DomainException;

/**
 * Raised when authentication fails. Deliberately identical for "no such user" and "wrong
 * password" so the API cannot be used to enumerate accounts.
 */
final class InvalidCredentials extends DomainException
{
    public static function create(): self
    {
        return new self('Invalid credentials.');
    }

    public function errorCode(): string
    {
        return 'AUTH_002';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
