<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Exception;

use App\Domain\Shared\Exception\DomainException;

/** The MFA challenge is unknown, already used, or expired. Generic; the user must log in again. */
final class MfaChallengeInvalid extends DomainException
{
    public static function create(): self
    {
        return new self('The MFA challenge is invalid or has expired.');
    }

    public function errorCode(): string
    {
        return 'MFA_004';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
