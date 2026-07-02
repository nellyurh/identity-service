<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\User;

use App\Domain\Identity\User\ValueObject\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_normalizes_to_lowercase_and_trims(): void
    {
        $this->assertSame('ada@unero.com', (new Email('  Ada@Unero.COM '))->value);
    }

    public function test_equality_is_by_normalized_value(): void
    {
        $this->assertTrue((new Email('a@b.com'))->equals(new Email('A@B.com')));
    }

    public function test_rejects_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email('not-an-email');
    }
}
