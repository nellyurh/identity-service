<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\User;

use App\Domain\Identity\User\ValueObject\Username;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    public function test_accepts_valid_and_normalizes(): void
    {
        $this->assertSame('ada_l.99', (new Username('Ada_L.99'))->value);
    }

    public function test_rejects_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Username('ab');
    }

    public function test_rejects_illegal_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Username('ada space');
    }
}
