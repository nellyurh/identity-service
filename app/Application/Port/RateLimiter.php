<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Fixed-window attempt throttling for abuse-prone operations. The adapter owns the backing store
 * (Redis in production, the configured cache in tests); callers deal only in opaque keys.
 */
interface RateLimiter
{
    /** True when $key has already used up $maxAttempts within its current window. */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /** Count one attempt against $key; the window opens on first hit and lasts $decaySeconds. */
    public function hit(string $key, int $decaySeconds): void;

    /** Seconds until $key's window resets (0 when not limited). */
    public function availableIn(string $key): int;

    /** Forget all attempts for $key. */
    public function clear(string $key): void;
}
