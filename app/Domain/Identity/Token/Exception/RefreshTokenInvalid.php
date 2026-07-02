<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token\Exception;

use App\Domain\Shared\Exception\DomainException;

/**
 * The presented refresh token cannot be used: unknown, expired, or already revoked. Mapped to
 * 401 so the client re-authenticates. The detail deliberately omits which condition failed —
 * we do not help a probe distinguish "expired" from "revoked" from "never existed".
 */
final class RefreshTokenInvalid extends DomainException
{
    public static function because(string $reason): self
    {
        $e = new self('The refresh token is invalid.');
        $e->detail = ['reason' => $reason];

        return $e;
    }

    public function errorCode(): string
    {
        return 'AUTH_011';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
