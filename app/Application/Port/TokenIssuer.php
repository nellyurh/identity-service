<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Auth\Result\IssuedAccessToken;
use DateTimeImmutable;

/** Mints signed access tokens. Adapter chooses the crypto (RS256); callers stay agnostic. */
interface TokenIssuer
{
    /**
     * @param  array<string,mixed>  $claims  extra claims (e.g. token_use, authz_ver, permissions)
     */
    public function issueAccessToken(string $subject, array $claims, DateTimeImmutable $now): IssuedAccessToken;
}
