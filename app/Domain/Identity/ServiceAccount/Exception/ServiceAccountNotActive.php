<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Exception;

use App\Domain\Identity\ServiceAccount\ServiceAccountStatus;
use App\Domain\Shared\Exception\DomainException;

/** Raised when a disabled service account attempts to authenticate. */
final class ServiceAccountNotActive extends DomainException
{
    public static function because(ServiceAccountStatus $status): self
    {
        $e = new self("Service account is {$status->value} and cannot authenticate.");
        $e->detail = ['status' => $status->value];

        return $e;
    }

    public function errorCode(): string
    {
        return 'SERVICE_002';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
