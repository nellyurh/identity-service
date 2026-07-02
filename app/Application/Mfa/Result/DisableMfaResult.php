<?php

declare(strict_types=1);

namespace App\Application\Mfa\Result;

final readonly class DisableMfaResult
{
    public function __construct(
        public bool $disabled,
    ) {}
}
