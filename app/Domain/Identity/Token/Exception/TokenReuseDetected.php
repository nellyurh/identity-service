<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token\Exception;

use App\Domain\Shared\Exception\DomainException;

/**
 * A refresh token that had already been rotated was presented again — the hallmark of a stolen
 * token being replayed. The caller responds by revoking the entire family (both the legitimate
 * holder and the attacker are forced to re-authenticate) and raises this. Mapped to 401.
 */
final class TokenReuseDetected extends DomainException
{
    public static function inFamily(string $familyId): self
    {
        $e = new self('Refresh token reuse detected; the session family has been revoked.');
        $e->detail = ['family_id' => $familyId];

        return $e;
    }

    public function errorCode(): string
    {
        return 'AUTH_012';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
