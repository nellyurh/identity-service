<?php

declare(strict_types=1);

namespace App\Domain\Identity\PasswordReset\Exception;

use App\Domain\Shared\Exception\DomainException;

/** The delivery reference is unknown, already materialised, used, or expired. Generic on purpose. */
final class PasswordResetDeliveryInvalid extends DomainException
{
    public static function create(): self
    {
        return new self('The password reset delivery reference is invalid or has expired.');
    }

    public function errorCode(): string
    {
        return 'RESET_001';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
