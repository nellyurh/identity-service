<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount\Result;

/**
 * The result of a successful client-credentials grant: a short-lived service access token and the
 * scopes it carries. No refresh token — a service re-authenticates with its credentials when the
 * token expires.
 */
final readonly class ServiceTokenResult
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public array $scopes,
    ) {}
}
