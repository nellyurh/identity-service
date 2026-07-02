<?php

declare(strict_types=1);

namespace App\Application\Mfa\Result;

final readonly class ConfirmTotpResult
{
    /** @param list<string> $recoveryCodes */
    public function __construct(
        public bool $enabled,
        public array $recoveryCodes,
    ) {}
}
