<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

use RuntimeException;

/**
 * Base for domain exceptions that map to the shared error envelope (ERROR_CATALOG.md).
 * Every subclass declares a stable machine-readable code (NAMESPACE_NNN) and an HTTP
 * status. Mirrors config-service's DomainException; hoisted to Domain\Shared because
 * identity-service has several aggregates that raise domain errors.
 */
abstract class DomainException extends RuntimeException
{
    /** @var array<string,mixed> */
    protected array $detail = [];

    abstract public function errorCode(): string;

    abstract public function httpStatus(): int;

    /** @return array<string,mixed> */
    public function detail(): array
    {
        return $this->detail;
    }
}
