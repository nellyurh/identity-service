<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use App\Application\Port\RateLimiter;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;

/**
 * RateLimiter over Laravel's cache-backed limiter: atomic increments against the configured cache
 * store (ElastiCache Redis in production, the array store in tests).
 */
final readonly class LaravelRateLimiter implements RateLimiter
{
    public function __construct(
        private CacheRateLimiter $limiter,
    ) {}

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->limiter->tooManyAttempts($key, $maxAttempts);
    }

    public function hit(string $key, int $decaySeconds): void
    {
        $this->limiter->hit($key, $decaySeconds);
    }

    public function availableIn(string $key): int
    {
        return $this->limiter->availableIn($key);
    }

    public function clear(string $key): void
    {
        $this->limiter->clear($key);
    }
}
