<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Role;

use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Role\Exception\SystemRoleImmutable;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\Role\ValueObject\RoleName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class RoleTest extends TestCase
{
    public function test_grant_is_idempotent_and_revoke_removes(): void
    {
        $role = Role::create(new RoleId((string) new Ulid), new RoleName('member'), null, false);
        $perm = new PermissionId((string) new Ulid);

        $role->grantPermission($perm);
        $role->grantPermission($perm);
        $this->assertCount(1, $role->permissions());
        $this->assertTrue($role->hasPermission($perm));

        $role->revokePermission($perm);
        $this->assertFalse($role->hasPermission($perm));
    }

    public function test_system_role_cannot_be_renamed(): void
    {
        $role = Role::create(new RoleId((string) new Ulid), new RoleName('super_admin'), 'root', true);

        $this->expectException(SystemRoleImmutable::class);
        $role->rename(new RoleName('root'));
    }
}
