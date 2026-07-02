<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\User;

use App\Domain\Identity\User\ValueObject\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UserIdTest extends TestCase
{
    public function test_accepts_a_ulid(): void
    {
        $ulid = (string) new Ulid;
        $this->assertSame($ulid, (new UserId($ulid))->value);
    }

    public function test_rejects_non_ulid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserId('not-a-ulid');
    }

    public function test_equality(): void
    {
        $ulid = (string) new Ulid;
        $this->assertTrue(UserId::fromString($ulid)->equals(new UserId($ulid)));
    }
}
