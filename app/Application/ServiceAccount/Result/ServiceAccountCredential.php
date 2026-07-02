<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount\Result;

/**
 * Returned once from create/rotate: the account view plus the plaintext client secret. The secret
 * is shown exactly once and never persisted (only its hash lives in the aggregate); callers must
 * capture it here or rotate again.
 */
final readonly class ServiceAccountCredential
{
    public function __construct(
        public ServiceAccountView $account,
        public string $secret,
    ) {}
}
