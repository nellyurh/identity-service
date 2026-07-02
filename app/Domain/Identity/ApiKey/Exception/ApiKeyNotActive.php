<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ApiKeyNotActive extends DomainException
{
    public static function cannotRotate(string $id): self
    {
        $e = new self('A revoked API key cannot be rotated.');
        $e->detail = ['api_key_id' => $id];

        return $e;
    }

    public function errorCode(): string
    {
        return 'APIKEY_004';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
