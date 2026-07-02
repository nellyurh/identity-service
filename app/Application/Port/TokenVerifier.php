<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Auth\Result\VerifiedToken;
use App\Domain\Identity\Token\Exception\TokenInvalid;
use DateTimeImmutable;

/** Verifies an access token's signature and temporal/audience claims. */
interface TokenVerifier
{
    /** @throws TokenInvalid */
    public function verify(string $jwt, DateTimeImmutable $now): VerifiedToken;
}
