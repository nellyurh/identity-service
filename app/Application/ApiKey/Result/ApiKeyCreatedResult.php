<?php

declare(strict_types=1);

namespace App\Application\ApiKey\Result;

/**
 * Returned once from create: the key view plus the full `unero_<env>_<prefix>.<secret>` string. The
 * full key is shown exactly once and never persisted (only the secret's hash lives in the aggregate).
 */
final readonly class ApiKeyCreatedResult
{
    public function __construct(
        public ApiKeyView $key,
        public string $fullKey,
    ) {}
}
