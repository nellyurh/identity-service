<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\Exception;

use App\Domain\Shared\Exception\DomainException;

/**
 * Presented API key could not authenticate. Deliberately generic: a missing/malformed header, an
 * unknown prefix, a wrong secret, an expired key, and a revoked key all surface identically so the
 * endpoint cannot be used to probe which keys exist or why one failed.
 */
final class ApiKeyAuthenticationFailed extends DomainException
{
    public static function create(): self
    {
        return new self('Invalid API key.');
    }

    public function errorCode(): string
    {
        return 'APIKEY_003';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
