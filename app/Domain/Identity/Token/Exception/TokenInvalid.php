<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token\Exception;

use App\Domain\Shared\Exception\DomainException;

/** An access token failed verification (signature, expiry, audience, or malformed). */
final class TokenInvalid extends DomainException
{
    public static function because(string $reason): self
    {
        $e = new self('The access token is invalid.');
        $e->detail = ['reason' => $reason];

        return $e;
    }

    public function errorCode(): string
    {
        return 'AUTH_010';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
