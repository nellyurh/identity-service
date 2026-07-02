<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ServiceAccountNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        $e = new self("No service account with id {$id}.");
        $e->detail = ['service_account_id' => $id];

        return $e;
    }

    public function errorCode(): string
    {
        return 'SERVICE_001';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
