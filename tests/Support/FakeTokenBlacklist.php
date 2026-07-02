<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Port\TokenBlacklist;
use DateTimeImmutable;

/**
 * In-memory TokenBlacklist for tests. Records blacklisted jtis (expiry is not enforced — tests
 * run instantaneously, so a recorded jti is treated as denied).
 */
final class FakeTokenBlacklist implements TokenBlacklist
{
    /** @var array<string,true> */
    public array $denied = [];

    public function blacklist(string $jti, DateTimeImmutable $expiresAt): void
    {
        $this->denied[$jti] = true;
    }

    public function isBlacklisted(string $jti): bool
    {
        return isset($this->denied[$jti]);
    }
}
