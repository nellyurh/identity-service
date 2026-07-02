<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Mfa;

use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class RecoveryCodeTest extends TestCase
{
    public function test_usable_until_consumed(): void
    {
        $now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
        $code = RecoveryCode::create((string) new Ulid, new UserId((string) new Ulid), hash('sha256', 'abc'), $now);

        $this->assertTrue($code->isUsable());

        $code->consume($now);
        $this->assertFalse($code->isUsable());
        $this->assertNotNull($code->usedAt());
    }
}
