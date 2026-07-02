<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Mfa\Rfc6238TotpProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class Rfc6238TotpProviderTest extends TestCase
{
    // base32 of the RFC 6238 SHA1 seed "12345678901234567890".
    private const string RFC_SEED = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private Rfc6238TotpProvider $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = new Rfc6238TotpProvider('Unero', 30, 6, 1);
    }

    private function at(int $t): DateTimeImmutable
    {
        return (new DateTimeImmutable)->setTimestamp($t);
    }

    /**
     * @return list<array{0:int,1:string}>
     */
    public static function rfcVectors(): array
    {
        return [
            [59, '287082'],
            [1111111109, '081804'],
            [1111111111, '050471'],
            [1234567890, '005924'],
            [2000000000, '279037'],
        ];
    }

    /**
     * @dataProvider rfcVectors
     */
    public function test_matches_rfc6238_vectors(int $t, string $code): void
    {
        $this->assertTrue($this->totp->verify(self::RFC_SEED, $code, $this->at($t)));
    }

    public function test_rejects_wrong_code(): void
    {
        $this->assertFalse($this->totp->verify(self::RFC_SEED, '000000', $this->at(59)));
    }

    public function test_tolerates_one_step_of_skew(): void
    {
        // 287082 is the code for the step at T=59; still valid one step later (window = 1).
        $this->assertTrue($this->totp->verify(self::RFC_SEED, '287082', $this->at(89)));
    }

    public function test_rejects_beyond_the_skew_window(): void
    {
        // Two steps away is outside the +/-1 window.
        $this->assertFalse($this->totp->verify(self::RFC_SEED, '287082', $this->at(119)));
    }

    public function test_rejects_malformed_secret(): void
    {
        $this->assertFalse($this->totp->verify('not-base32-!!!', '287082', $this->at(59)));
    }

    public function test_generated_secret_is_base32_and_long_enough(): void
    {
        $secret = $this->totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertGreaterThanOrEqual(32, strlen($secret)); // 20 bytes -> 32 base32 chars
    }

    public function test_provisioning_uri_shape(): void
    {
        $uri = $this->totp->provisioningUri(self::RFC_SEED, 'ada@unero.com');
        $this->assertStringStartsWith('otpauth://totp/Unero:', $uri);
        $this->assertStringContainsString('secret='.self::RFC_SEED, $uri);
        $this->assertStringContainsString('issuer=Unero', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
