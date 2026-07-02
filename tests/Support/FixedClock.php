<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Port\Clock;
use DateTimeImmutable;

final readonly class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $at) {}

    public function now(): DateTimeImmutable
    {
        return $this->at;
    }
}
