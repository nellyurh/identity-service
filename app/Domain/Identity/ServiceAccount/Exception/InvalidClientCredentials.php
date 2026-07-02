<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Exception;

use App\Domain\Shared\Exception\DomainException;

/**
 * The client-credentials grant failed. Deliberately generic: unknown client, wrong secret, and
 * disabled account all surface as the same error with no detail, so the endpoint can't be used to
 * enumerate service accounts or probe their status.
 */
final class InvalidClientCredentials extends DomainException
{
    public static function create(): self
    {
        return new self('Invalid client credentials.');
    }

    public function errorCode(): string
    {
        return 'SERVICE_004';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
