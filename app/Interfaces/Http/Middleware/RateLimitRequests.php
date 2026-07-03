<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use App\Application\Port\RateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Fixed-window rate limiting for public auth endpoints, applied as `ratelimit:<bucket>,<max>,<decay>`.
 * Two keys are counted per request: the caller's IP, and — when the body carries one — the targeted
 * identifier (email / client_id, normalised). The pair means neither IP rotation (identifier key) nor
 * one IP spraying many accounts (IP key) evades the limit. Exceeding either yields a generic
 * 429 RATE_001 before any credential or database work. Deliberately not audited per blocked request
 * (write amplification would turn the limiter into its own DoS vector); blocks are visible in the
 * platform's edge/infra metrics instead.
 */
final readonly class RateLimitRequests
{
    private const array IDENTIFIER_FIELDS = ['email', 'client_id'];

    public function __construct(
        private RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next, string $bucket, string $max = '10', string $decay = '60'): Response
    {
        $maxAttempts = max(1, (int) $max);
        $decaySeconds = max(1, (int) $decay);

        foreach ($this->keys($request, $bucket) as $key) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                throw new HttpException(429, 'RATE_001: Too many attempts. Try again later.');
            }
            $this->limiter->hit($key, $decaySeconds);
        }

        return $next($request);
    }

    /** @return list<string> */
    private function keys(Request $request, string $bucket): array
    {
        $keys = ['rl:'.$bucket.':ip:'.sha1((string) $request->ip())];

        foreach (self::IDENTIFIER_FIELDS as $field) {
            $value = $request->input($field);
            if (is_string($value) && $value !== '') {
                $keys[] = 'rl:'.$bucket.':id:'.sha1(strtolower(trim($value)));
                break;
            }
        }

        return $keys;
    }
}
