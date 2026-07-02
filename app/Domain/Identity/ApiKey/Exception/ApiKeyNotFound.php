<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ApiKeyNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        $e = new self('API key not found.');
        $e->detail = ['api_key_id' => $id];

        return $e;
    }

    public function errorCode(): string
    {
        return 'APIKEY_001';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
