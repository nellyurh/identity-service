<?php

declare(strict_types=1);

namespace App\Domain\Identity\PasswordReset\Exception;

use App\Domain\Shared\Exception\DomainException;

/** The presented reset token is unknown, already used, not yet materialised, or expired. Generic. */
final class PasswordResetTokenInvalid extends DomainException
{
    public static function create(): self
    {
        return new self('The password reset token is invalid or has expired.');
    }

    public function errorCode(): string
    {
        return 'RESET_002';
    }

    public function httpStatus(): int
    {
        return 400;
    }
}
