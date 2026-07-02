<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Exception;

use App\Domain\Shared\Exception\DomainException;

/** The submitted TOTP code did not verify against the enrolled secret. */
final class TotpCodeInvalid extends DomainException
{
    public static function create(): self
    {
        return new self('The verification code is incorrect.');
    }

    public function errorCode(): string
    {
        return 'MFA_002';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
