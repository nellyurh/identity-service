<?php

declare(strict_types=1);

namespace App\Application\Mfa\Result;

final readonly class RecoveryCodesResult
{
    /** @param list<string> $codes */
    public function __construct(
        public array $codes,
    ) {}
}
