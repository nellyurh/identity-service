<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Permission;

use App\Domain\Identity\Permission\ValueObject\PermissionName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PermissionNameTest extends TestCase
{
    public function test_splits_resource_and_action(): void
    {
        $p = new PermissionName('wallet.credit');
        $this->assertSame('wallet', $p->resource);
        $this->assertSame('credit', $p->action);
    }

    public function test_rejects_missing_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PermissionName('wallet');
    }
}
