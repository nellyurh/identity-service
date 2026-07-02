<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Domain\Shared\ValueObject\Actor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActorTest extends TestCase
{
    public function test_accepts_user_and_service_types(): void
    {
        $this->assertSame('user', (new Actor('u-1', 'user'))->type);
        $this->assertSame('service', (new Actor('svc-1', 'service'))->type);
    }

    public function test_rejects_empty_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Actor('', 'user');
    }

    public function test_rejects_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Actor('u-1', 'robot');
    }

    public function test_equality_is_by_id_and_type(): void
    {
        $a = new Actor('u-1', 'user');
        $this->assertTrue($a->equals(new Actor('u-1', 'user')));
        $this->assertFalse($a->equals(new Actor('u-1', 'service')));
        $this->assertFalse($a->equals(new Actor('u-2', 'user')));
    }
}
