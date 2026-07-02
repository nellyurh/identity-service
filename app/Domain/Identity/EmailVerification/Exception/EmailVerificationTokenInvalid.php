<?php

declare(strict_types=1);

namespace App\Domain\Identity\EmailVerification\Exception;

use App\Domain\Shared\Exception\DomainException;

/**
 * The presented email verification token is unknown, already used, or expired. Generic on purpose —
 * it does not distinguish which, and carries no user reference.
 */
final class EmailVerificationTokenInvalid extends DomainException
{
    public static function create(): self
    {
        return new self('The verification token is invalid or has expired.');
    }

    public function errorCode(): string
    {
        return 'VERIFICATION_001';
    }

    public function httpStatus(): int
    {
        return 400;
    }
}
