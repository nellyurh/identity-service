<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

use App\Application\Port\TokenBlacklist;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * TokenBlacklist over the framework cache repository (Redis in production, array in tests). The
 * entry TTL equals the token's remaining lifetime, so blacklist rows evict themselves exactly when
 * the token would have expired — the denylist never grows without bound. Using the cache contract
 * (not the raw Redis facade) keeps this adapter driver-agnostic and unit-testable.
 */
final readonly class CacheTokenBlacklist implements TokenBlacklist
{
    private const string PREFIX = 'token:blacklist:';

    public function __construct(private Cache $cache) {}

    public function blacklist(string $jti, DateTimeImmutable $expiresAt): void
    {
        $seconds = $expiresAt->getTimestamp() - time();
        if ($seconds <= 0) {
            return;
        }

        $this->cache->put(self::PREFIX.$jti, true, $seconds);
    }

    public function isBlacklisted(string $jti): bool
    {
        return $this->cache->has(self::PREFIX.$jti);
    }
}
