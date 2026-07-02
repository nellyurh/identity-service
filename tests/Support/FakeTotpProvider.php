<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Port\TotpProvider;
use DateTimeImmutable;

/**
 * Deterministic TOTP double for flow tests: generation is fixed and verification accepts a single
 * known code. The real RFC 6238 algorithm is proven separately in Rfc6238TotpProviderTest, so the
 * enrollment tests can exercise the aggregate/persistence/HTTP path without wall-clock timing.
 */
final class FakeTotpProvider implements TotpProvider
{
    public const string SECRET = 'JBSWY3DPEHPK3PXP';

    public const string VALID_CODE = '000000';

    public function generateSecret(): string
    {
        return self::SECRET;
    }

    public function provisioningUri(string $secret, string $accountName): string
    {
        return 'otpauth://totp/Unero:'.$accountName.'?secret='.$secret.'&issuer=Unero';
    }

    public function verify(string $secret, string $code, DateTimeImmutable $now): bool
    {
        return $code === self::VALID_CODE;
    }
}
